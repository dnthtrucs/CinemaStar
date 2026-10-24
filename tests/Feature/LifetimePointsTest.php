<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class LifetimePointsTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    public function test_payment_adds_both_counters_once_using_the_stored_tier(): void
    {
        config(['cinema.sepay.api_key' => 'lifetime-key', 'cinema.sepay.account_number' => '123', 'cinema.sepay.bank_code' => 'MBBank']);
        $user = User::factory()->create(['loyalty_points' => 300, 'lifetime_points' => 300]);
        $booking = $this->bookingFor($user);
        $this->actingAs($user)->post(route('payments.store', $booking), ['provider' => 'sepay'])->assertRedirect();
        $payment = Payment::firstOrFail();
        $payload = ['accountNumber' => '123', 'transferType' => 'in', 'transferAmount' => 80000,
            'content' => $payment->request_id, 'referenceCode' => 'LIFETIME-PAID'];
        $this->postJson(route('payments.sepay.webhook'), $payload, ['Authorization' => 'Apikey lifetime-key'])->assertOk();
        $this->postJson(route('payments.sepay.webhook'), $payload, ['Authorization' => 'Apikey lifetime-key'])->assertOk();

        $this->assertSame(9, $booking->fresh()->points_earned);
        $this->assertSame(309, (int) $user->fresh()->loyalty_points);
        $this->assertSame(309, $user->fresh()->lifetimePoints());
        $this->assertDatabaseHas('users', ['id' => $user->id, 'lifetime_points' => 309]);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_redeeming_and_returning_spendable_points_never_changes_the_lifetime_counter(): void
    {
        config(['cinema.sepay.api_key' => 'lifetime-key', 'cinema.sepay.account_number' => '123', 'cinema.sepay.bank_code' => 'MBBank']);
        $user = User::factory()->create(['loyalty_points' => 300, 'lifetime_points' => 300]);
        $booking = $this->bookingFor($user);

        $this->actingAs($user)->post(route('payments.store', $booking), ['provider' => 'sepay', 'points_to_use' => 10])->assertRedirect();
        $this->assertSame(290, (int) $user->fresh()->loyalty_points);
        $this->assertSame(300, $user->fresh()->lifetimePoints());
        $this->assertSame('silver', $user->fresh()->membershipTier()['key']);
        $this->delete(route('bookings.cancel', $booking))->assertRedirect();
        $this->assertSame(300, (int) $user->fresh()->loyalty_points);
        $this->assertSame(300, $user->fresh()->lifetimePoints());
    }

    public function test_completed_refund_removes_the_exact_award_from_both_counters_once(): void
    {
        $user = User::factory()->create(['loyalty_points' => 309, 'lifetime_points' => 309]);
        $booking = $this->bookingFor($user);
        $booking->update(['status' => 'confirmed', 'payment_status' => 'paid', 'points_earned' => 9]);
        $refund = RefundRequest::create(['booking_id' => $booking->id, 'requested_by' => $user->id,
            'reason' => 'Đổi lịch', 'amount' => 80000, 'status' => 'approved']);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patch(route('admin.refunds.update', $refund), ['status' => 'refunded'])->assertRedirect();
        $this->assertSame(300, (int) $user->fresh()->loyalty_points);
        $this->assertSame(300, $user->fresh()->lifetimePoints());
        $this->patch(route('admin.refunds.update', $refund), ['status' => 'refunded'])->assertUnprocessable();
        $this->assertSame(300, (int) $user->fresh()->loyalty_points);
        $this->assertSame(300, $user->fresh()->lifetimePoints());
    }

    public function test_refund_never_makes_the_lifetime_counter_negative(): void
    {
        $user = User::factory()->create(['loyalty_points' => 2, 'lifetime_points' => 2]);
        $booking = $this->bookingFor($user);
        $booking->update(['status' => 'confirmed', 'payment_status' => 'paid', 'points_earned' => 8]);
        $refund = RefundRequest::create(['booking_id' => $booking->id, 'requested_by' => $user->id,
            'reason' => 'Đổi lịch', 'amount' => 80000, 'status' => 'approved']);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patch(route('admin.refunds.update', $refund), ['status' => 'refunded'])->assertRedirect();
        $this->assertSame(0, (int) $user->fresh()->loyalty_points);
        $this->assertSame(0, $user->fresh()->lifetimePoints());
        $this->assertDatabaseHas('users', ['id' => $user->id, 'lifetime_points' => 0]);
    }
}
