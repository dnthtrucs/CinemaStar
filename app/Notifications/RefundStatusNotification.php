<?php

namespace App\Notifications;

use App\Models\RefundRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RefundStatusNotification extends Notification
{
    use Queueable;

    public function __construct(public RefundRequest $refund) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('CinemaStar - Cập nhật hoàn tiền')->greeting('Xin chào '.$notifiable->name)->line("Yêu cầu hoàn tiền cho đơn {$this->refund->booking->code} đã được cập nhật: ".strtoupper($this->refund->status))->line($this->refund->admin_note ?: 'Cảm ơn bạn đã sử dụng CinemaStar.');
    }

    public function toArray(object $notifiable): array
    {
        return ['type' => 'refund', 'booking_code' => $this->refund->booking->code, 'status' => $this->refund->status];
    }
}
