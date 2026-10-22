<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Movie;
use App\Models\Payment;
use App\Models\Showtime;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'movies' => Movie::count(),
            'showtimes' => Showtime::where('starts_at', '>=', now()->startOfDay())->count(),
            'bookings' => Booking::count(),
            'customers' => User::where('role', 'customer')->count(),
            'revenue' => Payment::where('status', 'success')->whereHas('booking', fn ($query) => $query->where('payment_status', 'paid'))->sum('amount'),
            'today_revenue' => Payment::where('status', 'success')->whereHas('booking', fn ($query) => $query->where('payment_status', 'paid'))->whereDate('paid_at', today())->sum('amount'),
        ];

        $recentBookings = Booking::with(['user', 'showtime.movie', 'showtime.room.cinema'])
            ->latest()->limit(8)->get();

        $monthlyRevenue = Payment::where('status', 'success')->whereHas('booking', fn ($query) => $query->where('payment_status', 'paid'))
            ->where('paid_at', '>=', now()->subMonths(5)->startOfMonth())
            ->get()
            ->groupBy(fn (Payment $payment) => $payment->paid_at->format('m/Y'))
            ->map->sum('amount');

        $latePayments = Payment::with('booking.user')->whereIn('response_code', Payment::MANUAL_REFUND_CODES)
            ->latest()->paginate(10, ['*'], 'late_payments_page');

        return view('admin.dashboard', compact('stats', 'recentBookings', 'monthlyRevenue', 'latePayments'));
    }
}
