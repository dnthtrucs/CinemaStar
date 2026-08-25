<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cinema extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'location', 'city', 'phone', 'description', 'image', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
