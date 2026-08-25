<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'showtime_id', 'code', 'quantity', 'subtotal', 'discount',
        'total_price', 'status', 'payment_status', 'payment_method', 'expires_at',
        'paid_at', 'cancelled_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:0', 'discount' => 'decimal:0', 'total_price' => 'decimal:0',
            'expires_at' => 'datetime', 'paid_at' => 'datetime', 'cancelled_at' => 'datetime',
        ];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function showtime() { return $this->belongsTo(Showtime::class); }
    public function tickets() { return $this->hasMany(Ticket::class); }
    public function payments() { return $this->hasMany(Payment::class); }

    /* One booking is checked in as a whole. This status is calculated live,
       so an unscanned ticket automatically becomes expired after the show ends. */
    public function getTicketStatusAttribute(): string
    {
        if ($this->status === 'cancelled') return 'cancelled';
        if ($this->payment_status !== 'paid' || $this->status !== 'confirmed') return 'unpaid';

        $tickets = $this->relationLoaded('tickets') ? $this->tickets : $this->tickets()->get();
        if ($tickets->isNotEmpty() && $tickets->every(fn (Ticket $ticket) => $ticket->status === 'used')) return 'checked_in';

        $showtime = $this->relationLoaded('showtime') ? $this->showtime : $this->showtime()->first();
        return $showtime?->ends_at?->isPast() ? 'expired' : 'valid';
    }

    public function getTicketStatusLabelAttribute(): string
    {
        return match ($this->ticket_status) {
            'checked_in' => 'Đã check-in', 'expired' => 'Đã hết hiệu lực',
            'valid' => 'Sẵn sàng vào rạp', 'unpaid' => 'Chưa thanh toán', 'cancelled' => 'Đã hủy',
        };
    }

    public function getTicketStatusBadgeAttribute(): string
    {
        return match ($this->ticket_status) {
            'checked_in' => 'success', 'expired', 'cancelled' => 'secondary',
            'valid' => 'primary', 'unpaid' => 'warning',
        };
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    public function isPayable(): bool { return $this->status === 'pending' && $this->payment_status === 'unpaid' && (! $this->expires_at || $this->expires_at->isFuture()); }
    public function isCancellable(): bool { return $this->status === 'pending' && $this->payment_status === 'unpaid'; }
}
