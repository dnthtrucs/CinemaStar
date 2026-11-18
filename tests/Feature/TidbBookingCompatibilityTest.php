<?php

namespace Tests\Feature;

use App\Models\Concession;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class TidbBookingCompatibilityTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    public function test_exclusive_price_lock_stores_the_original_combo_price(): void
    {
        config(['cinema.concession_shared_locks' => false]);
        [$showtime, $seats] = $this->showtimeWithSeats();
        $combo = Concession::create(['name' => 'Combo', 'price' => 50000, 'is_active' => true]);
        $queries = [];
        DB::listen(function ($event) use (&$queries) {
            if (str_contains($event->sql, 'from `concessions`')) {
                $queries[] = strtolower($event->sql);
            }
        });
        $booking = app(BookingService::class)->create(User::factory()->create(), $showtime, [$seats[0]->id], concessions: [$combo->id => 2]);
        $this->assertTrue(collect($queries)->contains(fn ($sql) => str_contains($sql, 'for update')));
        $this->assertFalse(collect($queries)->contains(fn ($sql) => str_contains($sql, 'lock in share mode')));
        $this->assertSame(180000, (int) $booking->total_price);
        $this->assertSame(50000, (int) $booking->concessions->first()->pivot->unit_price);
    }

    public function test_booking_without_a_combo_does_not_lock_the_concession_catalog(): void
    {
        config(['cinema.concession_shared_locks' => false]);
        $queries = [];
        DB::listen(function ($event) use (&$queries) {
            if (str_contains($event->sql, 'from `concessions`') && (str_contains($event->sql, 'for update') || str_contains($event->sql, 'lock in share mode'))) {
                $queries[] = $event->sql;
            }
        });
        $this->bookingFor();
        $this->assertSame([], $queries);
    }
}
