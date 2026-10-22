<?php

namespace Tests\Feature;

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
}
