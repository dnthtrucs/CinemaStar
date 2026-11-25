<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShowtimeCancelledNotification extends Notification
{
    public function __construct(public Booking $booking, public string $reason, public bool $email = false) {}

    public function via(object $notifiable): array
    {
        return $this->email ? ['mail'] : ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['type' => 'showtime_cancelled', 'title' => 'Suất chiếu đã hủy', 'booking_id' => $this->booking->id,
            'message' => 'Đơn '.$this->booking->code.': '.$this->reason.'. Vé không còn hiệu lực. Rạp sẽ liên hệ nếu đơn đã thanh toán.'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('CinemaStar - Thông báo hủy suất chiếu')->greeting('Xin chào '.$notifiable->name)
            ->line('Suất chiếu '.$this->booking->showtime->movie->title.' lúc '.$this->booking->showtime->starts_at->format('H:i d/m/Y').' đã hủy.')
            ->line('Lý do: '.$this->reason)->line('Mã đơn: '.$this->booking->code.'. Vé không còn hiệu lực. Rạp sẽ liên hệ để xử lý khoản đã thanh toán.')
            ->action('Xem đơn vé', route('bookings.show', $this->booking));
    }
}
