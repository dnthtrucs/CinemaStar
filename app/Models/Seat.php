<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Seat extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id', 'row', 'number', 'type', 'price_surcharge', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_surcharge' => 'decimal:0',
            'is_active' => 'boolean',
        ];
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function getLabelAttribute(): string
    {
        return $this->row.$this->number;
    }
}
