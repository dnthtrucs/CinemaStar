<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use App\Models\Voucher;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class VoucherTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    public function test_admin_can_leave_minimum_order_blank(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('admin.vouchers.store'), [
            'code' => 'SAVE', 'name' => 'Test', 'type' => 'fixed', 'value' => 10000, 'min_order' => '',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('vouchers', ['code' => 'SAVE', 'min_order' => 0]);
    }

    public function test_cancelled_and_expired_bookings_do_not_consume_voucher_uses(): void
    {
        $voucher = Voucher::create(['code' => 'SAVE', 'name' => 'Test', 'type' => 'fixed', 'value' => 10000, 'usage_limit' => 1]);
        $booking = $this->bookingFor(voucher: 'SAVE');
        $this->assertSame(0, (int) $voucher->fresh()->used_count);
        app(BookingService::class)->cancel($booking);
        $this->assertSame(0, (int) $voucher->fresh()->used_count);
        $again = app(BookingService::class)->create($booking->user, $booking->showtime, [$booking->showtime->room->seats->first()->id], 'SAVE');
        $again->update(['expires_at' => now()->subMinute()]);
        app(BookingService::class)->expirePendingBookings();
        $this->assertSame(0, (int) $voucher->fresh()->used_count);
    }

    public function test_successful_payment_consumes_voucher_once_and_prevents_reuse(): void
    {
        config(['cinema.payment_mode' => 'simulate']);
        $voucher = Voucher::create(['code' => 'SAVE', 'name' => 'Test', 'type' => 'fixed', 'value' => 10000]);
        $booking = $this->bookingFor(voucher: 'SAVE');
        $user = $booking->user;
        $this->actingAs($user)->post(route('payments.store', $booking), ['provider' => 'momo'])->assertRedirect();
        $payment = Payment::firstOrFail();
        $this->actingAs($user)->post(route('payments.simulate.complete', $payment), ['result' => 'success'])->assertRedirect();
        $this->assertSame(1, (int) $voucher->fresh()->used_count);
        $this->assertDatabaseHas('voucher_usages', ['voucher_id' => $voucher->id, 'user_id' => $user->id, 'booking_id' => $booking->id]);
        $this->actingAs($user)->post(route('bookings.store', $booking->showtime), [
            'seats' => [$booking->showtime->room->seats->last()->id], 'voucher_code' => 'SAVE',
        ])->assertSessionHasErrors('voucher_code');
    }

    public function test_pending_reservations_count_toward_the_usage_limit(): void
    {
        Voucher::create(['code' => 'SAVE', 'name' => 'Test', 'type' => 'fixed', 'value' => 10000, 'usage_limit' => 1]);
        $booking = $this->bookingFor(voucher: 'SAVE');
        $this->actingAs(User::factory()->create())->post(route('bookings.store', $booking->showtime), [
            'seats' => [$booking->showtime->room->seats->last()->id], 'voucher_code' => 'SAVE',
        ])->assertSessionHasErrors('voucher_code');
    }
}
