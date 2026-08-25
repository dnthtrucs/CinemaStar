<?php

namespace App\Notifications;

use App\Models\Booking;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceiptNotification extends Notification
{
    public function __construct(public Booking $booking)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing([
            'showtime.movie', 'showtime.room.cinema', 'tickets.seat',
        ]);
        $showtime = $booking->showtime;
        $seatLabels = $booking->tickets->pluck('seat.label')->filter()->join(', ');

        $verificationUrl = route('bookings.verify', [
            'booking' => $booking->id,
            'signature' => $this->bookingSignature(),
        ]);

        $message = (new MailMessage)
            ->subject('CinemaStar | Vé điện tử '.$booking->code)
            ->view('emails.payment-receipt', [
                'customer' => $notifiable,
                'booking' => $booking,
                'showtime' => $showtime,
                'seatLabels' => $seatLabels !== '' ? $seatLabels : 'Đang cập nhật',
            ]);

        $message->attachData(
            $this->makeQrPng($verificationUrl),
            'CinemaStar-QR-DON-'.$booking->code.'.png',
            ['mime' => 'image/png'],
        );

        return $message;
    }

    private function makeQrPng(string $verificationUrl): string
    {
        $qrCode = new QrCode(
            data: $verificationUrl,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 420,
            margin: 12,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(23, 23, 23),
            backgroundColor: new Color(255, 255, 255),
        );

        return (new PngWriter())->write($qrCode)->getString();
    }

    private function bookingSignature(): string
    {
        return hash_hmac(
            'sha256',
            $this->booking->id.'|'.$this->booking->code,
            (string) config('app.key'),
        );
    }
}
