<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class AccountSafetyTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    public static function unsettledPaymentStatuses(): array
    {
        return [['initiated'], ['pending'], ['failed']];
    }

    #[DataProvider('unsettledPaymentStatuses')]
    public function test_user_with_any_payment_reference_cannot_delete_their_account(string $status): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $booking = $this->bookingFor($user);
        $payment = Payment::create([
            'booking_id' => $booking->id, 'provider' => 'sepay', 'request_id' => 'PAY261023000000ABCDEF',
            'amount' => 80000, 'status' => $status,
        ]);

        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect(route('profile.edit'))->assertSessionHasErrorsIn('userDeletion', 'password');

        $this->assertNotNull($user->fresh());
        $this->assertNotNull($booking->fresh());
        $this->assertNotNull($payment->fresh());
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_with_only_cancelled_or_expired_bookings_and_no_payment_can_delete_their_account(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $booking = $this->bookingFor($user);
        app(BookingService::class)->cancel($booking);
        $expired = app(BookingService::class)->create($user, $booking->showtime, [$booking->showtime->room->seats->first()->id]);
        $expired->update(['expires_at' => now()->subMinute()]);
        app(BookingService::class)->expirePendingBookings();

        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');

        $this->assertNull($user->fresh());
        $this->assertNull($booking->fresh());
        $this->assertNull($expired->fresh());
        $this->assertGuest();
    }

    public function test_database_rejects_deleting_a_booking_that_has_a_payment(): void
    {
        $booking = $this->bookingFor();
        Payment::create([
            'booking_id' => $booking->id, 'provider' => 'sepay', 'request_id' => 'PAY261023000000ABCDEF',
            'amount' => 80000, 'status' => 'pending',
        ]);

        $this->expectException(QueryException::class);
        $booking->delete();
    }

    public function test_locked_user_loses_an_existing_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $user->update(['is_active' => false]);
        $this->get(route('dashboard'))->assertRedirect(route('login'))->assertSessionHas('status', 'Tài khoản đã bị khóa');
        $this->assertGuest();
    }

    public function test_user_with_paid_history_cannot_delete_their_account(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $booking = $this->bookingFor($user);
        $booking->update(['status' => 'confirmed', 'payment_status' => 'paid']);
        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect(route('profile.edit'))->assertSessionHasErrorsIn('userDeletion', 'password');
        $this->assertNotNull($user->fresh());
        $this->assertNotNull($booking->fresh());
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_with_refund_history_cannot_delete_their_account(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $booking = $this->bookingFor($user);
        RefundRequest::create(['booking_id' => $booking->id, 'requested_by' => $user->id, 'reason' => 'Test', 'amount' => 80000]);
        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect(route('profile.edit'))->assertSessionHasErrorsIn('userDeletion', 'password');
        $this->assertNotNull($user->fresh());
    }

    public function test_user_with_money_waiting_for_manual_refund_cannot_delete_their_account(): void
    {
        config(['cinema.sepay.api_key' => 'key', 'cinema.sepay.account_number' => '123', 'cinema.sepay.bank_code' => 'MBBank']);
        $user = User::factory()->create(['password' => 'password']);
        $booking = $this->bookingFor($user);
        $this->actingAs($user)->post(route('payments.store', $booking), ['provider' => 'sepay'])->assertRedirect();
        $payment = Payment::firstOrFail();
        $booking->update(['expires_at' => now()->subMinute()]);
        app(BookingService::class)->expirePendingBookings();
        $this->postJson(route('payments.sepay.webhook'), [
            'accountNumber' => '123', 'transferType' => 'in', 'transferAmount' => (int) $payment->amount,
            'content' => $payment->request_id, 'referenceCode' => 'LATE',
        ], ['Authorization' => 'Apikey key'])->assertUnprocessable();
        $this->assertSame('PAID_AFTER_EXPIRY', $payment->fresh()->response_code);

        $this->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect(route('profile.edit'))->assertSessionHasErrorsIn('userDeletion', 'password');
        $this->assertNotNull($user->fresh());
        $this->assertNotNull($payment->fresh());
        $this->assertAuthenticatedAs($user);
    }
}
