<?php

namespace App\Services;

use App\Models\Movie;
use Illuminate\Support\Facades\Cache;

class MovieScheduleService
{
    public function sync(): int
    {
        $today = today()->toDateString();
        $base = Movie::query()->where('auto_schedule', true)->whereNotNull('release_date');
        $count = (clone $base)->where('end_date', '<', $today)->where('status', '!=', 'stopped')->update(['status' => 'stopped']);
        $active = (clone $base)->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $today));
        $count += (clone $active)->where('release_date', '>', $today)->where('status', '!=', 'upcoming')->update(['status' => 'upcoming']);
        $count += (clone $active)->where('release_date', '<=', $today)->where('status', '!=', 'now_showing')->update(['status' => 'now_showing']);
        if ($count) {
            Cache::forget('home-page-data:v1');
        }

        return $count;
    }
}
