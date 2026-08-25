<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id', 'provider', 'request_id', 'transaction_id', 'amount',
        'status', 'response_code', 'payload', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:0',
            'payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
