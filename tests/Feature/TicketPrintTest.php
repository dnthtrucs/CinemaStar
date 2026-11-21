<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class TicketPrintTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    public function test_receipt_prints_all_seats_and_actual_discounted_total(): void
    {
        [$showtime, $seats] = $this->showtimeWithSeats();
        $user = User::factory()->create();
        $booking = app(BookingService::class)->create($user, $showtime, $seats->pluck('id')->all());
        $booking->update(['status' => 'confirmed', 'payment_status' => 'paid', 'discount' => 10000, 'points_used' => 5, 'total_price' => 175000]);
        $this->actingAs($user)->get(route('bookings.print', $booking))->assertForbidden();
        $this->get(route('bookings.show', $booking))->assertOk()->assertDontSee('>In vé</a>', false)->assertSee('nhân viên quét mã và in vé');
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)->get(route('bookings.print', $booking))->assertUnprocessable();
        $this->post(route('staff.tickets.scan'), ['ticket_token' => $booking->verificationUrl()])->assertRedirect(route('staff.tickets.index', ['code' => $booking->code]));
        $this->get(route('staff.tickets.index', ['code' => $booking->code]))->assertOk()->assertSee('In vé tại quầy');
        $this->get(route('bookings.print', $booking).'?preview=1')
            ->assertOk()->assertSee('80mm')->assertSee('A1, C1')->assertSee('190.000 VNĐ')
            ->assertSee('-10.000 VNĐ')->assertSee('-5.000 VNĐ')->assertSee('175.000 VNĐ')
            ->assertSee('Một mã vạch cho toàn bộ 2 ghế')->assertSee('data:image/png;base64,', false);
        $this->actingAs(User::factory()->create())->get(route('bookings.print', $booking))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('bookings.print', $booking))->assertOk();
    }

    public function test_unpaid_booking_cannot_be_printed(): void
    {
        $booking = $this->bookingFor();
        $this->actingAs(User::factory()->create(['role' => 'staff']))->get(route('bookings.print', $booking))->assertUnprocessable();
    }
}
