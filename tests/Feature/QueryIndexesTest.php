<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class QueryIndexesTest extends TestCase
{
    use CreatesCinemaBookings, DatabaseMigrations;

    public function test_query_indexes_support_hot_filters_and_rollback_preserves_orders(): void
    {
        $booking = $this->bookingFor();
        $indexes = [
            'bookings' => ['bookings_user_created_index', 'bookings_expiry_scan_index'],
            'payments' => ['payments_status_paid_booking_index'],
            'showtimes' => ['showtimes_status_starts_index'],
        ];
        foreach ($indexes as $table => $names) {
            foreach ($names as $name) {
                $this->assertTrue(Schema::hasIndex($table, $name), $name);
            }
        }

        $plan = DB::select('EXPLAIN SELECT id FROM bookings WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 10', [$booking->user_id]);
        $this->assertStringContainsString('bookings_user_created_index', $plan[0]->possible_keys);
        $this->assertSame('bookings_user_created_index', $plan[0]->key);

        $migration = require database_path('migrations/2026_10_25_000001_add_query_indexes.php');
        $migration->down();
        foreach ($indexes as $table => $names) {
            foreach ($names as $name) {
                $this->assertFalse(Schema::hasIndex($table, $name));
            }
        }
        $this->assertSame('pending', $booking->fresh()->status);
        $this->assertDatabaseHas('tickets', ['booking_id' => $booking->id]);
        $migration->up();
        $this->assertSame('pending', $booking->fresh()->status);
    }
}
