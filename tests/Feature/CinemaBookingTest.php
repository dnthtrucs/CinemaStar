<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CinemaBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_select_seats_and_create_a_booking(): void
    {
        [$showtime, $seats] = $this->showtimeWithSeats();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('bookings.store', $showtime), [
            'seats' => [$seats[0]->id, $seats[1]->id],
        ]);

        $booking = Booking::firstOrFail();
        $response->assertRedirect(route('bookings.show', $booking));
        $this->assertSame('pending', $booking->status);
        $this->assertSame(2, $booking->quantity);
        $this->assertSame(190000, (int) $booking->total_price);
        $this->assertDatabaseCount('tickets', 2);
    }

    public function test_a_reserved_seat_cannot_be_booked_twice(): void
    {
        [$showtime, $seats] = $this->showtimeWithSeats();
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $this->actingAs($firstUser)->post(route('bookings.store', $showtime), ['seats' => [$seats[0]->id]]);
        $response = $this->actingAs($secondUser)->post(route('bookings.store', $showtime), ['seats' => [$seats[0]->id]]);

        $response->assertSessionHasErrors('seats');
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_cancelling_an_unpaid_booking_releases_its_seats(): void
    {
        [$showtime, $seats] = $this->showtimeWithSeats();
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('bookings.store', $showtime), ['seats' => [$seats[0]->id]]);
        $booking = Booking::firstOrFail();

        $response = $this->actingAs($user)->delete(route('bookings.cancel', $booking));

        $response->assertRedirect(route('bookings.index'));
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_demo_payment_confirms_the_booking_idempotently(): void
    {
        config(['cinema.demo_payment_enabled' => true]);
        [$showtime, $seats] = $this->showtimeWithSeats();
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('bookings.store', $showtime), ['seats' => [$seats[0]->id]]);
        $booking = Booking::firstOrFail();

        $this->actingAs($user)->post(route('payments.store', $booking), ['provider' => 'demo']);
        $payment = Payment::firstOrFail();
        $response = $this->actingAs($user)->post(route('payments.demo.complete', $payment));

        $response->assertRedirect(route('bookings.show', $booking));
        $this->assertSame('success', $payment->fresh()->status);
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame('paid', $booking->fresh()->payment_status);
    }

    public function test_simulated_momo_payment_confirms_booking_and_activates_qr_ticket(): void
    {
        $this->assertSimulatedProviderConfirmsBooking('momo');
    }

    public function test_simulated_vnpay_payment_confirms_booking_and_activates_qr_ticket(): void
    {
        $this->assertSimulatedProviderConfirmsBooking('vnpay');
    }

    public function test_real_sandbox_provider_cannot_start_without_credentials(): void
    {
        config([
            'cinema.payment_mode' => 'sandbox',
            'cinema.vnpay.tmn_code' => null,
            'cinema.vnpay.hash_secret' => null,
        ]);
        [$showtime, $seats] = $this->showtimeWithSeats();
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('bookings.store', $showtime), ['seats' => [$seats[0]->id]]);
        $booking = Booking::firstOrFail();

        $response = $this->actingAs($user)
            ->from(route('payments.show', $booking))
            ->post(route('payments.store', $booking), ['provider' => 'vnpay']);

        $response->assertRedirect(route('payments.show', $booking));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_customer_cannot_access_admin_area(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($customer)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_paid_ticket_can_be_verified_with_its_private_qr_token(): void
    {
        [$showtime, $seats] = $this->showtimeWithSeats();
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('bookings.store', $showtime), ['seats' => [$seats[0]->id]]);

        $booking = Booking::firstOrFail();
        $booking->update(['status' => 'confirmed', 'payment_status' => 'paid']);
        $ticket = Ticket::firstOrFail();

        $this->get(route('tickets.verify', ['qrToken' => $ticket->qr_token]))
            ->assertOk()
            ->assertSee($ticket->code)
            ->assertSee('Vé hợp lệ')
            ->assertDontSee($user->email);
    }

    public function test_invalid_qr_token_returns_not_found(): void
    {
        $this->get(route('tickets.verify', ['qrToken' => str_repeat('a', 64)]))->assertNotFound();
    }

    private function assertSimulatedProviderConfirmsBooking(string $provider): void
    {
        config(['cinema.payment_mode' => 'simulate']);
        [$showtime, $seats] = $this->showtimeWithSeats();
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('bookings.store', $showtime), ['seats' => [$seats[0]->id]]);
        $booking = Booking::firstOrFail();

        $response = $this->actingAs($user)
            ->post(route('payments.store', $booking), ['provider' => $provider]);
        $payment = Payment::firstOrFail();

        $response->assertRedirect(route('payments.simulate', $payment));
        $this->actingAs($user)
            ->post(route('payments.simulate.complete', $payment), ['result' => 'success'])
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertSame('success', $payment->fresh()->status);
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame('paid', $booking->fresh()->payment_status);
        $this->assertSame($provider, $booking->fresh()->payment_method);

        $this->actingAs($user)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('data-ticket-qr', false);
    }

    private function showtimeWithSeats(): array
    {
        $cinema = Cinema::create([
            'name' => 'Rạp Test', 'slug' => 'rap-test', 'location' => 'Hà Nội',
            'city' => 'Hà Nội', 'is_active' => true,
        ]);
        $room = Room::create([
            'cinema_id' => $cinema->id, 'name' => 'Phòng 01', 'type' => '2D',
            'rows' => 3, 'seats_per_row' => 5, 'total_seats' => 15, 'is_active' => true,
        ]);
        $seats = collect([
            Seat::create(['room_id' => $room->id, 'row' => 'A', 'number' => 1, 'type' => 'standard', 'price_surcharge' => 0, 'is_active' => true]),
            Seat::create(['room_id' => $room->id, 'row' => 'C', 'number' => 1, 'type' => 'vip', 'price_surcharge' => 30000, 'is_active' => true]),
        ]);
        $movie = Movie::create([
            'title' => 'Phim Test', 'slug' => 'phim-test', 'description' => 'Nội dung phim dùng để kiểm thử hệ thống.',
            'genre' => 'Hành động', 'director' => 'Đạo diễn Test', 'duration' => 120,
            'age_rating' => 'T13', 'country' => 'Việt Nam', 'language' => 'Tiếng Việt',
            'status' => 'now_showing',
        ]);
        $showtime = Showtime::create([
            'movie_id' => $movie->id, 'room_id' => $room->id,
            'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addMinutes(135),
            'base_price' => 80000, 'format' => '2D', 'language' => 'Tiếng Việt', 'status' => 'scheduled',
        ]);

        return [$showtime, $seats];
    }
}
