<?php

namespace App\Notifications;

use App\Models\Booking;
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
                'verificationUrl' => $verificationUrl,
            ]);

        return $message;
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
