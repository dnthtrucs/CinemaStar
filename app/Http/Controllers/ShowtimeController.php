<?php

namespace App\Http\Controllers;

use App\Models\Concession;
use App\Models\Showtime;
use App\Services\BookingService;

class ShowtimeController extends Controller
{
    public function availability(Showtime $showtime, BookingService $bookingService)
    {
        $bookingService->expirePendingBookings($showtime->id);
        $open = $showtime->status === 'scheduled' && $showtime->starts_at->isFuture()
            && $showtime->movie->status !== 'stopped';

        return response()->json([
            'open' => $open,
            'unavailable' => $showtime->tickets()->pluck('seat_id')->map(fn ($id) => (int) $id)->values(),
            'inactive' => $showtime->room->seats()->where('is_active', false)->pluck('id'),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function show(Showtime $showtime, BookingService $bookingService)
    {
        abort_if($showtime->status !== 'scheduled' || $showtime->movie->status === 'stopped', 404);

        $bookingService->expirePendingBookings($showtime->id);
        $showtime->load(['movie', 'room.cinema', 'room.seats']);
        $bookedSeatIds = $showtime->tickets()->pluck('seat_id')->all();
        $seatRows = $showtime->room->seats->groupBy('row');
        $concessions = Concession::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();

        return view('showtimes.show', compact('showtime', 'seatRows', 'bookedSeatIds', 'concessions'));
    }
}
