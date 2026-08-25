<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\TicketCheckin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $booking = $request->filled('code')
            ? Booking::with(['user', 'showtime.movie', 'showtime.room.cinema', 'tickets.seat'])
                ->where('code', strtoupper(trim($request->string('code')->toString())))->first()
            : null;
        return view('admin.tickets.index', compact('booking'));
    }

    public function update(Booking $booking)
    {
        $booking->loadMissing('showtime', 'tickets');
        abort_unless($booking->payment_status === 'paid', 422, 'Đơn vé chưa được thanh toán.');
        abort_if($booking->showtime?->ends_at?->isPast(), 422, 'Suất chiếu đã kết thúc. Vé chưa check-in đã hết hiệu lực.');

        DB::transaction(function () use ($booking) {
            $validTickets = $booking->tickets->where('status', 'valid');
            abort_if($validTickets->isEmpty(), 422, 'Đơn vé này đã được check-in hoặc không còn hiệu lực.');
            foreach ($validTickets as $ticket) {
                $ticket->update(['status' => 'used', 'checked_in_at' => now()]);
                TicketCheckin::create(['ticket_id' => $ticket->id, 'staff_id' => auth()->id(), 'checked_in_at' => now()]);
            }
            ActivityLog::record('booking.checked_in', "Check-in đơn {$booking->code}", $booking);
        });
        return back()->with('success', "Check-in thành công cho toàn bộ ghế trong đơn {$booking->code}.");
    }
}
