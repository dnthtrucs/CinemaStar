<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class StreamingRevenueTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    public function test_a_refund_during_export_does_not_skip_other_receipts_between_batches(): void
    {
        [$showtime] = $this->showtimeWithSeats();
        $user = User::factory()->create();
        $bookings = [];
        foreach (['LATEST', 'OLDER'] as $code) {
            $bookings[] = Booking::create([
                'user_id' => $user->id, 'showtime_id' => $showtime->id, 'code' => 'BK'.$code,
                'quantity' => 1, 'subtotal' => 80000, 'total_price' => 80000,
                'status' => 'confirmed', 'payment_status' => 'paid',
            ]);
        }
        $payments = [];
        for ($i = 0; $i < 504; $i++) {
            $payments[] = [
                'booking_id' => $i === 0 ? $bookings[0]->id : $bookings[1]->id,
                'provider' => 'sepay', 'request_id' => (string) Str::uuid(),
                'amount' => 1000, 'status' => 'success',
                'paid_at' => now()->subMinutes($i === 0 ? 1 : 2),
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        Payment::insert($payments);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $refunded = false;
        DB::listen(function ($query) use (&$refunded, $bookings) {
            if (! $refunded && str_starts_with($query->sql, 'select')
                && str_contains($query->sql, 'from `payments`') && str_contains($query->sql, 'limit 500')) {
                $refunded = true;
                $bookings[0]->update(['payment_status' => 'refunded']);
            }
        });

        $csv = $this->get(route('admin.reports.revenue'))->assertOk()->streamedContent();
        $this->assertTrue($refunded);
        $this->assertSame(503, substr_count($csv, 'BKOLDER'), 'An earlier refund must not shift offsets and skip a different receipt.');
        $this->assertCount(505, array_filter(explode("\n", trim($csv))));
    }

    public function test_csv_defers_reading_receipts_and_streams_every_row_across_chunks(): void
    {
        [$showtime] = $this->showtimeWithSeats();
        $booking = Booking::create([
            'user_id' => User::factory()->create()->id, 'showtime_id' => $showtime->id,
            'code' => 'BKSTREAM', 'quantity' => 1, 'subtotal' => 80000,
            'total_price' => 80000, 'status' => 'confirmed', 'payment_status' => 'paid',
        ]);
        $receipts = [];
        for ($i = 0; $i < 503; $i++) {
            $receipts[] = [
                'booking_id' => $booking->id, 'provider' => 'sepay',
                'request_id' => (string) Str::uuid(), 'amount' => 1000,
                'status' => 'success', 'paid_at' => now()->subMinute(),
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        Payment::insert($receipts);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            if (str_starts_with($query->sql, 'select') && str_contains($query->sql, 'from `payments`')) {
                $queries[] = $query->sql;
            }
        });

        $response = $this->get(route('admin.reports.revenue'))->assertOk()->assertDownload('doanh-thu.csv');
        $this->assertCount(0, $queries, 'CSV must not hydrate the entire report before streaming.');
        $csv = $response->streamedContent();
        $lines = array_filter(explode("\n", trim($csv)));
        $this->assertCount(504, $lines);
        $this->assertSame(503, substr_count($csv, 'BKSTREAM'));
        $this->assertGreaterThanOrEqual(2, count($queries));
        foreach ($queries as $sql) {
            $this->assertStringContainsString('limit 500', $sql);
        }
    }
}
