<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Request;

class BookingTicketController extends Controller
{
    public function verify(Booking $booking, string $signature)
    {
        abort_unless(hash_equals($this->signature($booking), $signature), 403, 'Mã QR không hợp lệ.');

        return view('tickets.verify-booking', [
            'booking' => $this->loadBooking($booking),
        ]);
    }

    public function print(Request $request, Booking $booking)
    {
        abort_unless(
            $request->user()
                && ($request->user()->id === $booking->user_id || $request->user()->role === 'admin'),
            403,
        );

        $booking = $this->loadBooking($booking);

        return view('bookings.print', [
            'booking' => $booking,
            'qrDataUri' => 'data:image/svg+xml;base64,'.base64_encode(
                $this->makeQrSvg(route('bookings.verify', [
                    'booking' => $booking->id,
                    'signature' => $this->signature($booking),
                ]))
            ),
        ]);
    }

    private function loadBooking(Booking $booking): Booking
    {
        return $booking->loadMissing([
            'user',
            'showtime.movie',
            'showtime.room.cinema',
            'tickets.seat',
        ]);
    }

    private function signature(Booking $booking): string
    {
        return hash_hmac('sha256', $booking->id.'|'.$booking->code, (string) config('app.key'));
    }

    private function makeQrSvg(string $url): string
    {
        $qrCode = new QrCode(
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 420,
            margin: 12,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(23, 23, 23),
            backgroundColor: new Color(255, 255, 255),
        );

        return (new SvgWriter())->write($qrCode)->getString();
    }
}
