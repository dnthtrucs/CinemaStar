<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Ticket;
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
                $this->checkInTicket($ticket);
            }

            ActivityLog::record('booking.checked_in', "Check-in đơn {$booking->code}", $booking);
        });

        return back()->with('success', "Check-in thành công cho toàn bộ ghế trong đơn {$booking->code}.");
    }

    public function scan(Request $request)
    {
        $value = $request->validate([
            'ticket_token' => ['required', 'string', 'max:2048'],
        ])['ticket_token'];

        $token = $this->extractQrToken($value);
        if (! $token) {
            return back()->with('error', 'Mã QR không đúng định dạng vé CinemaStar.');
        }

        $ticket = Ticket::query()->where('qr_token', $token)->first();
        if (! $ticket) {
            return back()->with('error', 'Không tìm thấy vé tương ứng với mã QR này.');
        }

        $this->checkInTicket($ticket);

        return back()->with('success', "Check-in thành công vé {$ticket->fresh()->code}.");
    }

    private function checkInTicket(Ticket $ticket): void
    {
        DB::transaction(function () use ($ticket) {
            $ticket = Ticket::query()
                ->with(['booking.showtime', 'showtime'])
                ->lockForUpdate()
                ->findOrFail($ticket->id);

            abort_unless($ticket->booking->payment_status === 'paid', 422, 'Vé chưa được thanh toán.');
            abort_if($ticket->showtime?->ends_at?->isPast(), 422, 'Suất chiếu đã kết thúc. Vé chưa check-in đã hết hiệu lực.');
            abort_unless($ticket->status === 'valid', 422, 'Vé này đã check-in hoặc không còn hiệu lực.');

            $checkedInAt = now();
            $ticket->update([
                'status' => 'used',
                'checked_in_at' => $checkedInAt,
            ]);

            TicketCheckin::create([
                'ticket_id' => $ticket->id,
                'staff_id' => auth()->id(),
                'checked_in_at' => $checkedInAt,
            ]);

            ActivityLog::record('ticket.checked_in', "Check-in vé {$ticket->code}", $ticket);
        });
    }

    private function extractQrToken(string $value): ?string
    {
        return preg_match('/(?<![A-Fa-f0-9])[A-Fa-f0-9]{64}(?![A-Fa-f0-9])/', $value, $matches)
            ? strtolower($matches[0])
            : null;
    }
}
