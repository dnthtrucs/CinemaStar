<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class ShowtimeSafetyTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    public function test_held_seats_block_schedule_changes_but_allow_price_changes(): void
    {
        $booking = $this->bookingFor();
        $showtime = $booking->showtime;
        $data = $showtime->only(['movie_id', 'room_id', 'base_price', 'format', 'language', 'status']);
        $data['starts_at'] = $showtime->starts_at->copy()->addHour()->toDateTimeString();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->put(route('admin.showtimes.update', $showtime), $data)->assertRedirect()->assertSessionHas('error');
        $this->assertTrue($showtime->fresh()->starts_at->equalTo($showtime->starts_at));

        $data['starts_at'] = $showtime->starts_at->toDateTimeString();
        $data['base_price'] = 90000;
        $this->put(route('admin.showtimes.update', $showtime), $data)->assertRedirect(route('admin.showtimes.index'))->assertSessionHasNoErrors();
        $this->assertSame(90000, (int) $showtime->fresh()->base_price);
    }

    public function test_expired_reservations_release_seats_before_schedule_update(): void
    {
        $booking = $this->bookingFor();
        $booking->update(['expires_at' => now()->subMinute()]);
        $showtime = $booking->showtime;
        $data = $showtime->only(['movie_id', 'room_id', 'base_price', 'format', 'language', 'status']);
        $data['starts_at'] = $showtime->starts_at->copy()->addHour()->toDateTimeString();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->put(route('admin.showtimes.update', $showtime), $data)->assertRedirect(route('admin.showtimes.index'));
        $this->assertSame('expired', $booking->fresh()->status);
        $this->assertSame(0, $booking->tickets()->count());
    }

    public function test_cancelling_showtime_warns_about_paid_bookings(): void
    {
        $booking = $this->bookingFor();
        $booking->update(['status' => 'confirmed', 'payment_status' => 'paid']);
        $showtime = $booking->showtime;
        $data = $showtime->only(['movie_id', 'room_id', 'base_price', 'format', 'language']);
        $data += ['starts_at' => $showtime->starts_at->toDateTimeString(), 'status' => 'cancelled'];
        $this->actingAs(User::factory()->create(['role' => 'admin']))->put(route('admin.showtimes.update', $showtime), $data)->assertRedirect()->assertSessionHas('error');
        $this->assertSame('cancelled', $showtime->fresh()->status);
        $this->assertSame('paid', $booking->fresh()->payment_status);
    }

    public function test_cancelling_showtime_releases_held_bookings_so_they_cannot_be_paid(): void
    {
        config(['cinema.payment_mode' => 'simulate']);
        $user = User::factory()->create(['loyalty_points' => 50]);
        $booking = $this->bookingFor($user);
        $this->actingAs($user)->post(route('payments.store', $booking), ['provider' => 'momo', 'points_to_use' => 10])->assertRedirect();
        $payment = Payment::firstOrFail();
        $showtime = $booking->showtime;
        $data = $showtime->only(['movie_id', 'room_id', 'base_price', 'format', 'language']);
        $data += ['starts_at' => $showtime->starts_at->toDateTimeString(), 'status' => 'cancelled'];

        $this->actingAs(User::factory()->create(['role' => 'admin']))->put(route('admin.showtimes.update', $showtime), $data)->assertRedirect(route('admin.showtimes.index'));

        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame(0, $booking->tickets()->count());
        $this->assertSame(50, (int) $user->fresh()->loyalty_points);
        $this->actingAs($user)->get(route('payments.show', $booking))->assertUnprocessable();
        $this->post(route('payments.simulate.complete', $payment), ['result' => 'success'])->assertRedirect();
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertNotSame('success', $payment->fresh()->status);
    }

    public function test_late_transfer_for_cancelled_showtime_is_flagged_for_refund(): void
    {
        config(['cinema.sepay.api_key' => 'key', 'cinema.sepay.account_number' => '123', 'cinema.sepay.bank_code' => 'MBBank']);
        $booking = $this->bookingFor();
        $this->actingAs($booking->user)->post(route('payments.store', $booking), ['provider' => 'sepay'])->assertRedirect();
        $payment = Payment::firstOrFail();
        // Suất bị hủy trực tiếp (không qua form) trong lúc đơn còn giữ chỗ.
        $booking->showtime->update(['status' => 'cancelled']);

        $this->postJson(route('payments.sepay.webhook'), [
            'accountNumber' => '123', 'transferType' => 'in', 'transferAmount' => 80000,
            'content' => $payment->request_id, 'referenceCode' => 'CANCELLED1',
        ], ['Authorization' => 'Apikey key'])->assertUnprocessable();

        $this->assertSame('SHOWTIME_CANCELLED', $payment->fresh()->response_code);
        $this->assertSame('pending', $booking->fresh()->status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'payment.paid_for_cancelled_showtime', 'subject_id' => $payment->id]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.dashboard'))
            ->assertOk()->assertSee($payment->request_id)->assertSee('Suất chiếu đã hủy');
    }
}
