<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Services\TicketBarcode;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceiptNotification extends Notification
{
    public function __construct(public Booking $booking, public ?string $previousCode = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing([
            'showtime.movie', 'showtime.room.cinema', 'tickets.seat',
            'concessions',
        ]);
        $showtime = $booking->showtime;
        $seatLabels = $booking->tickets->pluck('seat.label')->filter()->join(', ');

        $barcodePng = app(TicketBarcode::class)->png($booking);

        $message = (new MailMessage)
            ->subject(($this->previousCode ? 'Vé mới sau đổi suất ' : 'Xác nhận đặt vé ').$booking->code.' - CinemaStar')
            ->view(['html' => 'emails.payment-receipt', 'text' => 'emails.payment-receipt-text'], [
                'customer' => $notifiable,
                'previousCode' => $this->previousCode,
                'booking' => $booking,
                'showtime' => $showtime,
                'seatLabels' => $seatLabels !== '' ? $seatLabels : 'Đang cập nhật',
                'barcodePng' => $barcodePng,
            ]);

        return $message;
    }
}
