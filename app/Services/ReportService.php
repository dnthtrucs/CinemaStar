<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ReportService
{
    private const TIMEZONE = 'Asia/Ho_Chi_Minh';

    public function dashboard(?CarbonInterface $at = null): array
    {
        [$from, $to] = $this->window($at);

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'timezone' => self::TIMEZONE],
            'daily' => $this->dailyRevenue($to),
            'top_movies' => $this->topMovies($to),
            'occupancy' => $this->occupancy($to),
            'revenue_split' => $this->revenueSplit($to),
        ];
    }

    public function dailyRevenue(?CarbonInterface $at = null): array
    {
        [$from, $to] = $this->window($at);
        $case = 'CASE';
        $bindings = [];
        $days = [];

        // Boundaries are converted in PHP: MySQL need not have timezone tables.
        // This also works when the application's storage timezone is UTC.
        for ($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
            $date = $day->toDateString();
            $days[] = ['date' => $date, 'revenue' => 0];
            $case .= ' WHEN payments.paid_at >= ? AND payments.paid_at < ? THEN ?';
            array_push($bindings, $this->stored($day), $this->stored($day->addDay()), $date);
        }

        $amounts = $this->cash($from, $to)
            ->selectRaw($case.' END AS report_day', $bindings)
            ->selectRaw('SUM(payments.amount) AS revenue')
            ->groupBy('report_day')->pluck('revenue', 'report_day');

        return array_map(fn (array $day) => ['date' => $day['date'], 'revenue' => (int) ($amounts[$day['date']] ?? 0)], $days);
    }

    public function topMovies(?CarbonInterface $at = null): array
    {
        [$from, $to] = $this->window($at);

        return DB::query()->fromSub($this->allocatedCash($from, $to), 'orders')
            ->join('showtimes', 'showtimes.id', '=', 'orders.showtime_id')
            ->join('movies', 'movies.id', '=', 'showtimes.movie_id')
            ->select('movies.id', 'movies.title')->selectRaw('SUM(orders.cash - orders.concessions) AS revenue')
            ->groupBy('movies.id', 'movies.title')->orderByDesc('revenue')->orderBy('movies.id')
            ->limit(10)->get()->map(fn ($row) => [
                'movie_id' => (int) $row->id, 'title' => $row->title, 'revenue' => (int) $row->revenue,
            ])->all();
    }

    public function occupancy(?CarbonInterface $at = null): array
    {
        [$from, $to] = $this->window($at);
        $showtimes = fn () => DB::table('showtimes')
            ->where('showtimes.status', 'scheduled')
            ->where('showtimes.ends_at', '>=', $this->stored($from))
            ->where('showtimes.ends_at', '<=', $this->stored($to));

        $sold = DB::table('tickets')->join('bookings', 'bookings.id', '=', 'tickets.booking_id')
            ->where('bookings.payment_status', 'paid')
            ->whereIn('tickets.showtime_id', $showtimes()->select('showtimes.id'))
            ->whereExists(fn (Builder $query) => $query->selectRaw('1')->from('payments')
                ->whereColumn('payments.booking_id', 'bookings.id')->where('payments.status', 'success'))
            ->select('tickets.showtime_id')->selectRaw('COUNT(*) AS sold')->groupBy('tickets.showtime_id');

        // Each screening contributes its room capacity once. EXISTS prevents
        // several receipts for one booking from multiplying physical tickets.
        return $showtimes()
            ->join('rooms', 'rooms.id', '=', 'showtimes.room_id')
            ->join('movies', 'movies.id', '=', 'showtimes.movie_id')
            ->leftJoinSub($sold, 'sales', fn ($join) => $join->on('sales.showtime_id', '=', 'showtimes.id'))
            ->select('movies.id', 'movies.title')
            ->selectRaw('SUM(COALESCE(sales.sold, 0)) AS sold, SUM(rooms.total_seats) AS capacity')
            ->groupBy('movies.id', 'movies.title')->orderBy('movies.title')->orderBy('movies.id')
            ->get()->map(fn ($row) => [
                'movie_id' => (int) $row->id, 'title' => $row->title,
                'sold' => (int) $row->sold, 'capacity' => (int) $row->capacity,
                'percentage' => $row->capacity > 0 ? round(100 * $row->sold / $row->capacity, 2) : 0.0,
            ])->all();
    }

    public function revenueSplit(?CarbonInterface $at = null): array
    {
        [$from, $to] = $this->window($at);
        $split = DB::query()->fromSub($this->allocatedCash($from, $to), 'orders')
            ->selectRaw('COALESCE(SUM(cash), 0) AS total')
            ->selectRaw('COALESCE(SUM(concessions), 0) AS concessions')
            ->first();
        $total = (int) $split->total;
        $concessions = (int) $split->concessions;

        return ['tickets' => $total - $concessions, 'concessions' => $concessions, 'total' => $total];
    }

    private function allocatedCash(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        $cashByBooking = $this->cash($from, $to)
            ->select('bookings.id', 'bookings.showtime_id', 'bookings.subtotal', 'bookings.concessions_total')
            ->selectRaw('SUM(payments.amount) AS cash')
            ->groupBy('bookings.id', 'bookings.showtime_id', 'bookings.subtotal', 'bookings.concessions_total');

        // Both reports use the same price snapshots and round once per order.
        // Tickets receive the remainder, so every paid dong is accounted for.
        return DB::query()->fromSub($cashByBooking, 'orders')
            ->select('orders.id', 'orders.showtime_id', 'orders.cash')
            ->selectRaw('CASE WHEN subtotal > 0 THEN ROUND(cash * LEAST(GREATEST(concessions_total, 0), subtotal) / subtotal, 0) ELSE 0 END AS concessions');
    }

    private function cash(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return DB::table('payments')->join('bookings', 'bookings.id', '=', 'payments.booking_id')
            ->where('payments.status', 'success')->where('bookings.payment_status', 'paid')
            ->where('payments.paid_at', '>=', $this->stored($from))
            ->where('payments.paid_at', '<=', $this->stored($to));
    }

    private function window(?CarbonInterface $at): array
    {
        $to = CarbonImmutable::instance($at ?? now())->setTimezone(self::TIMEZONE);

        return [$to->startOfDay()->subDays(29), $to];
    }

    private function stored(CarbonInterface $date): string
    {
        return CarbonImmutable::instance($date)->setTimezone(config('app.timezone', 'UTC'))->format('Y-m-d H:i:s');
    }
}
