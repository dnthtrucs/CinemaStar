<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = ['booking_id', 'showtime_id', 'seat_id', 'code', 'unit_price', 'qr_token', 'status', 'checked_in_at'];
    protected function casts(): array { return ['unit_price' => 'decimal:0', 'checked_in_at' => 'datetime']; }

    public function booking() { return $this->belongsTo(Booking::class); }
    public function showtime() { return $this->belongsTo(Showtime::class); }
    public function seat() { return $this->belongsTo(Seat::class); }

    public function getDisplayStatusAttribute(): string
    {
        if ($this->status === 'used') return 'checked_in';
        if ($this->booking?->payment_status === 'paid' && $this->showtime?->ends_at?->isPast()) return 'expired';
        return 'valid';
    }
}
