<?php

namespace App\Http\Controllers;

use App\Models\Showtime;
use App\Services\BookingService;

class ShowtimeController extends Controller
{
    public function show(Showtime $showtime, BookingService $bookingService)
    {
        abort_if($showtime->status !== 'scheduled', 404);

        $bookingService->expirePendingBookings($showtime->id);
        $showtime->load(['movie', 'room.cinema', 'room.seats']);
        $bookedSeatIds = $showtime->tickets()->pluck('seat_id')->all();
        $seatRows = $showtime->room->seats->groupBy('row');

        return view('showtimes.show', compact('showtime', 'seatRows', 'bookedSeatIds'));
    }
}
