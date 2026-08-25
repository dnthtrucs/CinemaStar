<?php

namespace App\Http\Controllers;

use App\Models\Booking;

class DashboardController extends Controller
{
    public function index()
    {
        if (auth()->user()->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        $bookings = Booking::query()
            ->where('user_id', auth()->id())
            ->with(['showtime.movie', 'showtime.room.cinema', 'tickets.seat'])
            ->latest()
            ->limit(5)
            ->get();

        $stats = [
            'total' => Booking::where('user_id', auth()->id())->count(),
            'upcoming' => Booking::where('user_id', auth()->id())
                ->where('status', 'confirmed')
                ->whereHas('showtime', fn ($query) => $query->where('starts_at', '>', now()))
                ->count(),
            'pending' => Booking::where('user_id', auth()->id())->where('status', 'pending')->count(),
        ];

        return view('dashboard', compact('bookings', 'stats'));
    }
}
