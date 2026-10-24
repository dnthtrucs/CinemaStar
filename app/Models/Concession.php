<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Concession extends Model
{
    protected $fillable = ['name', 'description', 'price', 'image_path', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['price' => 'integer', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function bookings()
    {
        return $this->belongsToMany(Booking::class, 'booking_concessions')->withPivot(['name', 'quantity', 'unit_price'])->withTimestamps();
    }
}
