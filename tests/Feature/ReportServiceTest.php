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
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    private User $customer;

    private CarbonImmutable $reportAt;

    protected function setUp(): void
    {
        parent::setUp();

        // Exercise Vietnam midnight while the application's persisted dates use UTC.
        config(['app.timezone' => 'UTC']);
        $this->reportAt = CarbonImmutable::parse('2026-10-24 00:30:00', 'Asia/Ho_Chi_Minh');
        $this->travelTo($this->reportAt);
        $this->customer = User::factory()->create();
        $cinema = Cinema::create(['name' => 'Rạp báo cáo', 'slug' => 'rap-bao-cao', 'location' => 'Hà Nội', 'city' => 'Hà Nội', 'is_active' => true]);
        $this->room = Room::create(['cinema_id' => $cinema->id, 'name' => 'Phòng 01', 'type' => '2D', 'rows' => 2, 'seats_per_row' => 5, 'total_seats' => 10, 'is_active' => true]);
    }

    public function test_daily_revenue_has_thirty_vietnam_days_zeros_and_exact_boundaries(): void
    {
        $showtime = $this->showtime();
        $start = $this->reportAt->startOfDay()->subDays(29);
        $this->receipt($this->booking($showtime), 10000, $start);
        $this->receipt($this->booking($showtime), 20000, $this->reportAt->startOfDay()->subSecond());
        $this->receipt($this->booking($showtime), 30000, $this->reportAt->startOfDay());
        $this->receipt($this->booking($showtime), 40000, $this->reportAt);
        $this->receipt($this->booking($showtime), 900000, $start->subSecond());
        $this->receipt($this->booking($showtime), 900000, $this->reportAt->addSecond());
        $this->receipt($this->booking($showtime, ['payment_status' => 'refunded']), 900000, $this->reportAt);
        $this->receipt($this->booking($showtime, ['payment_status' => 'unpaid']), 900000, $this->reportAt);
        $this->receipt($this->booking($showtime), 900000, $this->reportAt, 'pending');
        $this->receipt($this->booking($showtime), 900000, $this->reportAt, 'failed');

        $days = app(ReportService::class)->dailyRevenue($this->reportAt);

        $this->assertCount(30, $days);
        $this->assertSame(['date' => '2026-09-25', 'revenue' => 10000], $days[0]);
        $this->assertSame(['date' => '2026-10-23', 'revenue' => 20000], $days[28]);
        $this->assertSame(['date' => '2026-10-24', 'revenue' => 70000], $days[29]);
        $this->assertSame(['date' => '2026-09-26', 'revenue' => 0], $days[1]);
        $this->assertSame(100000, array_sum(array_column($days, 'revenue')));
    }

    public function test_vietnam_storage_timezone_uses_the_same_local_calendar_boundaries(): void
    {
        config(['app.timezone' => 'Asia/Ho_Chi_Minh']);
        $showtime = $this->showtime();
        $this->receipt($this->booking($showtime), 10000, $this->reportAt->startOfDay()->subSecond());
        $this->receipt($this->booking($showtime), 20000, $this->reportAt->startOfDay());

        $days = app(ReportService::class)->dailyRevenue($this->reportAt);

        $this->assertSame(10000, $days[28]['revenue']);
        $this->assertSame(20000, $days[29]['revenue']);
    }

    public function test_top_ten_movies_sum_cash_without_multiplying_by_tickets_or_other_receipts(): void
    {
        $winner = $this->showtime();
        $booking = $this->booking($winner, ['quantity' => 2]);
        $this->tickets($booking, 2);
        $this->receipt($booking, 150000, $this->reportAt);
        $this->receipt($booking, 50000, $this->reportAt);
        for ($index = 1; $index <= 11; $index++) {
            $this->receipt($this->booking($this->showtime()), $index * 1000, $this->reportAt);
        }
        $excluded = $this->showtime();
        $this->receipt($this->booking($excluded, ['payment_status' => 'refunded']), 900000, $this->reportAt);
        $this->receipt($this->booking($excluded, ['payment_status' => 'unpaid']), 900000, $this->reportAt);
        $this->receipt($this->booking($excluded), 900000, $this->reportAt->subDays(30));
        $this->receipt($this->booking($excluded), 900000, $this->reportAt, 'failed');

        $movies = app(ReportService::class)->topMovies($this->reportAt);

        $this->assertCount(10, $movies);
        $this->assertSame($winner->movie_id, $movies[0]['movie_id']);
        $this->assertSame(200000, $movies[0]['revenue']);
        $this->assertSame(3000, $movies[9]['revenue']);
        $this->assertNotContains($excluded->movie_id, array_column($movies, 'movie_id'));
    }

    public function test_occupancy_counts_capacity_once_and_only_paid_tickets_for_finished_shows(): void
    {
        $start = $this->reportAt->startOfDay()->subDays(29);
        $first = $this->showtime(['ends_at' => $this->stored($start)]);
        $second = $this->showtime(['movie_id' => $first->movie_id, 'ends_at' => $this->stored($this->reportAt)]);
        $paid = $this->booking($first, ['quantity' => 2]);
        $this->tickets($paid, 2);
        // Prepaid tickets still occupy seats even when money arrived before this period.
        $this->receipt($paid, 100000, $start->subDay());
        $this->receipt($paid, 10000, $this->reportAt);
        $used = $this->booking($second);
        $this->tickets($used, 1, 'used');
        $this->receipt($used, 80000, $this->reportAt);
        foreach (['refunded', 'unpaid'] as $state) {
            $booking = $this->booking($second, ['payment_status' => $state]);
            $this->tickets($booking, 1);
            $this->receipt($booking, 80000, $this->reportAt);
        }
        $withoutSuccess = $this->booking($second);
        $this->tickets($withoutSuccess, 1);
        $this->receipt($withoutSuccess, 80000, $this->reportAt, 'failed');
        foreach ([
            ['ends_at' => $this->stored($start->subSecond())],
            ['ends_at' => $this->stored($this->reportAt->addSecond())],
            ['ends_at' => $this->stored($this->reportAt->subMinute()), 'status' => 'cancelled'],
        ] as $attributes) {
            $showtime = $this->showtime($attributes + ['movie_id' => $first->movie_id]);
            $booking = $this->booking($showtime);
            $this->tickets($booking, 1);
            $this->receipt($booking, 80000, $this->reportAt);
        }
        $empty = $this->showtime(['ends_at' => $this->stored($this->reportAt->subHour())]);

        $rows = collect(app(ReportService::class)->occupancy($this->reportAt))->keyBy('movie_id');

        $this->assertCount(2, $rows);
        $this->assertSame(3, $rows[$first->movie_id]['sold']);
        $this->assertSame(20, $rows[$first->movie_id]['capacity']);
        $this->assertSame(15.0, $rows[$first->movie_id]['percentage']);
        $this->assertSame(0, $rows[$empty->movie_id]['sold']);
        $this->assertSame(10, $rows[$empty->movie_id]['capacity']);
        $this->assertEquals(0.0, $rows[$empty->movie_id]['percentage']);
    }

    public function test_cash_split_uses_snapshots_after_discounts_and_keeps_every_dong(): void
    {
        $showtime = $this->showtime();
        $booking = $this->booking($showtime, ['subtotal' => 100000, 'concessions_total' => 33333, 'discount' => 20000, 'points_used' => 9, 'total_price' => 71000]);
        $this->receipt($booking, 56001, $this->reportAt);
        $this->receipt($booking, 14000, $this->reportAt);
        // Both rounded receipt shares would be 23,334; grouping per booking is 23,333.
        $this->receipt($this->booking($showtime, ['subtotal' => 0, 'concessions_total' => 0]), 50, $this->reportAt);
        foreach (['refunded', 'unpaid'] as $state) {
            $this->receipt($this->booking($showtime, ['payment_status' => $state, 'concessions_total' => 100000]), 900000, $this->reportAt);
        }
        $this->receipt($this->booking($showtime), 900000, $this->reportAt->subDays(30));
        $this->receipt($this->booking($showtime), 900000, $this->reportAt, 'failed');

        $split = app(ReportService::class)->revenueSplit($this->reportAt);

        $this->assertSame(['tickets' => 46718, 'concessions' => 23333, 'total' => 70051], $split);
        $this->assertSame($split['total'], $split['tickets'] + $split['concessions']);
    }

    public function test_dashboard_is_ok_and_charts_have_zero_values_without_payments(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.dashboard'))->assertOk()
            ->assertViewHas('reports', fn (array $reports) => count($reports['daily']) === 30
                && array_sum(array_column($reports['daily'], 'revenue')) === 0
                && $reports['top_movies'] === [] && $reports['occupancy'] === []
                && $reports['revenue_split'] === ['tickets' => 0, 'concessions' => 0, 'total' => 0])
            ->assertSee('admin-dashboard-data', false)->assertSee('Doanh thu 30 ngày');
    }

    private function showtime(array $attributes = []): Showtime
    {
        $movieId = $attributes['movie_id'] ?? Movie::create([
            'title' => 'Phim '.Str::uuid(), 'slug' => (string) Str::uuid(), 'description' => 'Phim báo cáo',
            'genre' => 'Hành động', 'director' => 'Đạo diễn', 'duration' => 120,
            'age_rating' => 'T13', 'country' => 'Việt Nam', 'language' => 'Tiếng Việt', 'status' => 'now_showing',
        ])->id;

        $endsAt = CarbonImmutable::parse($attributes['ends_at'] ?? $this->stored($this->reportAt->subHour()), config('app.timezone'));

        return Showtime::create($attributes + [
            'movie_id' => $movieId, 'room_id' => $this->room->id,
            'starts_at' => $this->stored($endsAt->subHours(2)),
            'ends_at' => $this->stored($endsAt),
            'base_price' => 80000, 'format' => '2D', 'language' => 'Tiếng Việt', 'status' => 'scheduled',
        ]);
    }

    private function booking(Showtime $showtime, array $attributes = []): Booking
    {
        return Booking::create($attributes + [
            'user_id' => $this->customer->id, 'showtime_id' => $showtime->id,
            'code' => 'BK'.Str::upper(Str::random(16)), 'quantity' => 1,
            'subtotal' => 80000, 'concessions_total' => 0, 'discount' => 0, 'total_price' => 80000,
            'status' => 'confirmed', 'payment_status' => 'paid',
        ]);
    }

    private function receipt(Booking $booking, int $amount, CarbonImmutable $at, string $status = 'success'): Payment
    {
        return Payment::create([
            'booking_id' => $booking->id, 'provider' => 'sepay', 'request_id' => (string) Str::uuid(),
            'transaction_id' => (string) Str::uuid(), 'amount' => $amount, 'status' => $status, 'paid_at' => $this->stored($at),
        ]);
    }

    private function tickets(Booking $booking, int $count, string $status = 'valid'): void
    {
        for ($index = 0; $index < $count; $index++) {
            $position = Seat::count();
            $seat = Seat::create(['room_id' => $this->room->id, 'row' => chr(65 + intdiv($position, 5)), 'number' => $position % 5 + 1, 'type' => 'standard', 'price_surcharge' => 0, 'is_active' => true]);
            Ticket::create(['booking_id' => $booking->id, 'showtime_id' => $booking->showtime_id, 'seat_id' => $seat->id,
                'code' => Str::upper(Str::random(20)), 'qr_token' => Str::random(64), 'unit_price' => 80000, 'status' => $status]);
        }
    }

    private function stored(CarbonImmutable $at): string
    {
        return $at->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }
}
