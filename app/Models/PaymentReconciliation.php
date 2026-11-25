<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentReconciliation extends Model
{
    protected $fillable = ['payment_id', 'resolved_by', 'bank_reference', 'note'];

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
