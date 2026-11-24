<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingExchange extends Model
{
    public const STATUS_LABELS = ['pending' => 'Chờ xử lý', 'approved' => 'Đã đổi', 'rejected' => 'Từ chối', 'withdrawn' => 'Khách đã rút'];

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function original()
    {
        return $this->belongsTo(Showtime::class, 'from_showtime_id');
    }

    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['seat_ids' => 'array', 'snapshot' => 'array', 'handled_at' => 'datetime'];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function target()
    {
        return $this->belongsTo(Showtime::class, 'to_showtime_id');
    }
}
