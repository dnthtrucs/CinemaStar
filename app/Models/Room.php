<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'cinema_id', 'name', 'type', 'rows', 'seats_per_row', 'total_seats', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function cinema()
    {
        return $this->belongsTo(Cinema::class);
    }

    public function seats()
    {
        return $this->hasMany(Seat::class)->orderBy('row')->orderBy('number');
    }

    public function showtimes()
    {
        return $this->hasMany(Showtime::class);
    }
}
