<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Movie extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'slug', 'original_title', 'description', 'poster', 'trailer_url',
        'genre', 'director', 'actors', 'duration', 'age_rating', 'release_date',
        'country', 'language', 'status', 'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'is_featured' => 'boolean',
        ];
    }

    public function showtimes()
    {
        return $this->hasMany(Showtime::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
