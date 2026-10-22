<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherUsage extends Model
{
    protected $fillable = ['voucher_id', 'booking_id', 'user_id', 'discount_amount'];

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
