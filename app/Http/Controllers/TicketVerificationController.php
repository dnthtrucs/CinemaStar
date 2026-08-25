<?php

namespace App\Http\Controllers;

use App\Models\Ticket;

class TicketVerificationController extends Controller
{
    public function show(string $qrToken)
    {
        $ticket = Ticket::query()
            ->with(['booking', 'showtime.movie', 'showtime.room.cinema', 'seat'])
            ->where('qr_token', $qrToken)
            ->firstOrFail();

        return view('tickets.verify', compact('ticket'));
    }
}
