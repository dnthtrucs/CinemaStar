<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'subtitle', 'image_path', 'button_label',
        'button_url', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function getImageUrlAttribute(): string
    {
        return str_starts_with($this->image_path, '/catalog-assets/')
            ? asset(ltrim($this->image_path, '/'))
            : asset('storage/'.$this->image_path);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderByDesc('id');
    }
}
