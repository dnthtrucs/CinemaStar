<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class ScopedExpiryTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    public function test_visiting_my_tickets_expires_only_my_orders_and_keeps_other_customers_for_the_scheduler(): void
    {
        [$showtime, $seats] = $this->showtimeWithSeats();
        $customer = User::factory()->create(['loyalty_points' => 0]);
        $other = User::factory()->create(['loyalty_points' => 0]);
        $service = app(BookingService::class);
        $mine = $service->create($customer, $showtime, [$seats[0]->id]);
        $theirs = $service->create($other, $showtime, [$seats[1]->id]);
        foreach ([$mine, $theirs] as $booking) {
            $booking->update(['expires_at' => now()->subMinute(), 'points_used' => 3]);
        }

        $this->actingAs($customer)->get(route('bookings.index'))->assertOk();

        $this->assertSame('expired', $mine->fresh()->status);
        $this->assertSame('pending', $theirs->fresh()->status);
        $this->assertSame(3, $customer->fresh()->loyalty_points);
        $this->assertSame(0, $other->fresh()->loyalty_points);
        $this->assertDatabaseMissing('tickets', ['booking_id' => $mine->id]);
        $this->assertDatabaseHas('tickets', ['booking_id' => $theirs->id]);
        $this->assertSame(1, $service->expirePendingBookings());
        $this->assertSame('expired', $theirs->fresh()->status);
        $this->assertSame(3, $other->fresh()->loyalty_points);
        $this->assertSame(0, $service->expirePendingBookings());
    }
}
