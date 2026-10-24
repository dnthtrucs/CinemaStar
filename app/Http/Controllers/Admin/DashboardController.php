<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Movie;
use App\Models\Payment;
use App\Models\Showtime;
use App\Models\User;
use App\Services\ReportService;

class DashboardController extends Controller
{
    public function index(ReportService $reportService)
    {
        $reports = $reportService->dashboard();
        $stats = [
            'movies' => Movie::count(),
            'showtimes' => Showtime::where('starts_at', '>=', now()->startOfDay())->count(),
            'bookings' => Booking::count(),
            'customers' => User::where('role', 'customer')->count(),
            'revenue' => Payment::where('status', 'success')->whereHas('booking', fn ($query) => $query->where('payment_status', 'paid'))->sum('amount'),
            'today_revenue' => $reports['daily'][array_key_last($reports['daily'])]['revenue'] ?? 0,
        ];

        $recentBookings = Booking::with(['user', 'showtime.movie', 'showtime.room.cinema'])
            ->latest()->limit(8)->get();

        $latePayments = Payment::with('booking.user')->awaitingManualRefund()
            ->latest()->paginate(10, ['*'], 'late_payments_page');

        return view('admin.dashboard', compact('stats', 'recentBookings', 'reports', 'latePayments'));
    }
}
