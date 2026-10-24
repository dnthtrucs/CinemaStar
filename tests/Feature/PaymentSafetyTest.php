<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class PaymentSafetyTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    public function test_cancelled_simulation_cannot_be_confirmed_later(): void
    {
        config(['cinema.payment_mode' => 'simulate']);
        $booking = $this->bookingFor();
        $this->actingAs($booking->user)->post(route('payments.store', $booking), ['provider' => 'momo'])->assertRedirect();
        $payment = Payment::firstOrFail();
        $this->post(route('payments.simulate.complete', $payment), ['result' => 'cancelled'])->assertRedirect();
        $this->post(route('payments.simulate.complete', $payment), ['result' => 'success'])->assertUnprocessable();
        $this->assertSame('pending', $booking->fresh()->status);
        $this->assertSame('failed', $payment->fresh()->status);
    }

    public function test_excessive_points_are_a_form_validation_error(): void
    {
        config(['cinema.payment_mode' => 'simulate']);
        $user = User::factory()->create(['loyalty_points' => 5]);
        $booking = $this->bookingFor($user);
        $this->actingAs($user)->from(route('payments.show', $booking))->post(route('payments.store', $booking), [
            'provider' => 'momo', 'points_to_use' => 10,
        ])->assertRedirect(route('payments.show', $booking))->assertSessionHasErrors('points_to_use')->assertSessionHasInput('points_to_use', 10);
        $this->assertSame(5, (int) $user->fresh()->loyalty_points);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_late_transfer_is_logged_and_shown_for_manual_refund_without_rebooking(): void
    {
        config(['cinema.sepay.api_key' => 'key', 'cinema.sepay.account_number' => '123', 'cinema.sepay.bank_code' => 'MBBank']);
        $booking = $this->bookingFor();
        $this->actingAs($booking->user)->post(route('payments.store', $booking), ['provider' => 'sepay'])->assertRedirect();
        $payment = Payment::firstOrFail();
        $booking->update(['expires_at' => now()->subMinute()]);
        app(BookingService::class)->expirePendingBookings();

        foreach ([1, 2] as $attempt) {
            $this->postJson(route('payments.sepay.webhook'), [
                'accountNumber' => '123', 'transferType' => 'in', 'transferAmount' => 80000,
                'content' => 'Chuyen khoan '.$payment->request_id, 'referenceCode' => 'LATE1',
            ], ['Authorization' => 'Apikey key'])->assertUnprocessable();
        }
        $this->assertSame('PAID_AFTER_EXPIRY', $payment->fresh()->response_code);
        $this->assertSame('expired', $booking->fresh()->status);
        $this->assertSame(0, $booking->tickets()->count());
        $this->assertDatabaseHas('activity_logs', ['action' => 'payment.paid_after_expiry', 'subject_id' => $payment->id]);
        $this->assertSame(1, ActivityLog::where('action', 'payment.paid_after_expiry')->count());
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.dashboard'))
            ->assertOk()->assertSee('Cần xử lý hoàn tiền')->assertSee($payment->request_id);
    }

    public function test_expiry_returns_points_and_does_not_touch_paid_bookings(): void
    {
        $user = User::factory()->create(['loyalty_points' => 40]);
        $booking = $this->bookingFor($user);
        $booking->forceFill(['points_used' => 10, 'expires_at' => now()->subMinute()])->save();
        $service = app(BookingService::class);
        $this->assertSame(1, $service->expirePendingBookings());
        $this->assertSame(0, $service->expirePendingBookings());
        $this->assertSame(50, (int) $user->fresh()->loyalty_points);
        $this->assertSame(0, $booking->tickets()->count());

        $paid = $service->create($user, $booking->showtime, [$booking->showtime->room->seats->first()->id]);
        $paid->update(['status' => 'confirmed', 'payment_status' => 'paid', 'expires_at' => now()->subMinute()]);
        $this->assertFalse($service->expirePendingBooking($paid->id));
        $this->assertSame('confirmed', $paid->fresh()->status);
        $this->assertSame(1, $paid->tickets()->count());
    }

    public function test_late_transfer_with_points_is_flagged_even_after_points_are_returned(): void
    {
        config(['cinema.sepay.api_key' => 'key', 'cinema.sepay.account_number' => '123', 'cinema.sepay.bank_code' => 'MBBank']);
        $user = User::factory()->create(['loyalty_points' => 50]);
        $booking = $this->bookingFor($user);
        $this->actingAs($user)->post(route('payments.store', $booking), ['provider' => 'sepay', 'points_to_use' => 10])->assertRedirect();
        $payment = Payment::firstOrFail();
        $booking->update(['expires_at' => now()->subMinute()]);
        app(BookingService::class)->expirePendingBookings();
        $this->postJson(route('payments.sepay.webhook'), [
            'accountNumber' => '123', 'transferType' => 'in', 'transferAmount' => 70000,
            'content' => $payment->request_id, 'referenceCode' => 'LATEPOINTS',
        ], ['Authorization' => 'Apikey key'])->assertUnprocessable();
        $this->assertSame('PAID_AFTER_EXPIRY', $payment->fresh()->response_code);
        $this->assertSame(50, (int) $user->fresh()->loyalty_points);
        $this->assertSame(0, $booking->tickets()->count());
    }
}
