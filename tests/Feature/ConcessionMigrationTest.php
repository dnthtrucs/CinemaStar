<?php

namespace Tests\Feature;

use App\Models\Concession;
use Database\Seeders\ConcessionSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class ConcessionMigrationTest extends TestCase
{
    use CreatesCinemaBookings, DatabaseMigrations;

    public function test_upgrade_and_rollback_preserve_existing_bookings_and_seed_keeps_operator_prices(): void
    {
        $booking = $this->bookingFor();
        $before = (array) DB::table('bookings')->find($booking->id);
        unset($before['concessions_total']);
        $migration = require database_path('migrations/2026_10_24_000002_create_concessions_and_booking_concessions.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('concessions'));
        $this->assertFalse(Schema::hasTable('booking_concessions'));
        $this->assertSame($before, (array) DB::table('bookings')->find($booking->id));
        $migration->up();
        $after = (array) DB::table('bookings')->find($booking->id);
        $this->assertSame(0, (int) $after['concessions_total']);
        unset($after['concessions_total']);
        $this->assertSame($before, $after);
        $this->seed(ConcessionSeeder::class);
        $item = Concession::firstOrFail();
        $item->update(['price' => 12345]);
        $this->seed(ConcessionSeeder::class);
        $this->assertSame(4, Concession::count());
        $this->assertSame(12345, $item->fresh()->price);
        $migration->down();
        $this->assertSame($before, (array) DB::table('bookings')->find($booking->id));
        $migration->up();
    }
}
