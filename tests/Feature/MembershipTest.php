<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class MembershipTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    private function memberBookings(int $points): array
    {
        config(['cinema.sepay.api_key' => 'key', 'cinema.sepay.account_number' => '123', 'cinema.sepay.bank_code' => 'MBBank']);
        $user = User::factory()->create(['loyalty_points' => $points]);
        $history = $this->bookingFor($user);
        $history->update(['status' => 'confirmed', 'payment_status' => 'paid', 'points_earned' => $points]);
        $seat = $history->showtime->room->seats->last();
        $seat->update(['price_surcharge' => 0]);
        $current = app(BookingService::class)->create($user, $history->showtime, [$seat->id]);

        return [$user, $history, $current];
    }

    public function test_redemption_keeps_tier_and_refunded_history_does_not_count(): void
    {
        [$user, $history, $current] = $this->memberBookings(300);
        $this->actingAs($user)->post(route('payments.store', $current), ['provider' => 'sepay', 'points_to_use' => 10])->assertRedirect();
        $this->assertSame(290, (int) $user->fresh()->loyalty_points);
        $this->assertSame(300, $user->fresh()->lifetimePoints());
        $this->assertSame('silver', $user->fresh()->membershipTier()['key']);
        $history->update(['payment_status' => 'refunded']);
        $this->assertSame(0, $user->fresh()->lifetimePoints());
        $this->assertSame('bronze', $user->fresh()->membershipTier()['key']);
    }

    public static function rates(): array
    {
        return [[299, 8], [300, 9], [1000, 12]];
    }

    #[DataProvider('rates')]
    public function test_previous_tier_multiplier_is_applied_once_and_refund_removes_exact_award(int $points, int $award): void
    {
        [$user, $history, $booking] = $this->memberBookings($points);
        $this->actingAs($user)->post(route('payments.store', $booking), ['provider' => 'sepay'])->assertRedirect();
        $payment = Payment::firstOrFail();
        $payload = ['accountNumber' => '123', 'transferType' => 'in', 'transferAmount' => 80000,
            'content' => $payment->request_id, 'referenceCode' => 'MEMBERSHIP-1'];
        $this->postJson(route('payments.sepay.webhook'), $payload, ['Authorization' => 'Apikey key'])->assertOk();
        $this->postJson(route('payments.sepay.webhook'), $payload, ['Authorization' => 'Apikey key'])->assertOk();
        $this->assertSame($award, $booking->fresh()->points_earned);
        $this->assertSame($points + $award, (int) $user->fresh()->loyalty_points);
        $refund = RefundRequest::create(['booking_id' => $booking->id, 'requested_by' => $user->id, 'reason' => 'Đổi lịch', 'amount' => 80000, 'status' => 'approved']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->patch(route('admin.refunds.update', $refund), ['status' => 'refunded'])->assertRedirect();
        $this->assertSame($points, (int) $user->fresh()->loyalty_points);
        $this->assertSame($points, $user->fresh()->lifetimePoints());
    }

    public function test_membership_is_visible_in_profile_dashboard_checkout_and_admin_users(): void
    {
        [$user, $history, $booking] = $this->memberBookings(300);
        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('Hạng Bạc')->assertSee('Điểm tích lũy');
        $this->get('/dashboard')->assertOk()->assertSee('Hạng Bạc');
        $this->get(route('payments.show', $booking))->assertOk()->assertSee('Hạng Bạc — tích điểm x1.2');
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/users')->assertOk()->assertSee('Bạc');
    }
}
