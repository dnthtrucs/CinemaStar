<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\TicketBarcode;
use Illuminate\Http\Request;

class BookingTicketController extends Controller
{
    public function verify(Request $request, Booking $booking)
    {
        abort_unless(hash_equals($booking->verificationSignature(), (string) $request->query('signature')), 403, 'Mã QR không hợp lệ.');

        return view('tickets.verify-booking', [
            'booking' => $this->loadBooking($booking),
        ]);
    }

    public function print(Request $request, Booking $booking)
    {
        abort_unless(
            $request->user()
                && in_array($request->user()->role, ['admin', 'staff'], true),
            403,
        );
        abort_unless($request->user()->canOperateCinema((int) $booking->showtime->room->cinema_id), 403, 'Bạn không phụ trách rạp này.');
        abort_unless($booking->status === 'confirmed' && $booking->payment_status === 'paid', 422, 'Đơn vé chưa được thanh toán.');

        $booking = $this->loadBooking($booking);
        abort_unless($booking->ticket_status === 'checked_in' && $booking->showtime->status === 'scheduled', 422, 'Nhân viên cần quét mã hoặc xác nhận check-in trước khi in vé.');

        return view('bookings.print', [
            'booking' => $booking,
            'barcodeDataUri' => app(TicketBarcode::class)->dataUri($booking),
        ]);
    }

    private function loadBooking(Booking $booking): Booking
    {
        return $booking->loadMissing([
            'user',
            'showtime.movie',
            'showtime.room.cinema',
            'tickets.seat',
            'concessions',
        ]);
    }
}
