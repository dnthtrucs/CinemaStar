<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Showtime;

class HomeController extends Controller
{
    public function index()
    {
        $banners = Banner::query()->active()->ordered()->get();

        $featuredMovies = Movie::query()
            ->where('status', 'now_showing')
            ->orderByDesc('is_featured')
            ->orderByDesc('release_date')
            ->limit(8)
            ->get();

        $upcomingMovies = Movie::query()
            ->where('status', 'upcoming')
            ->orderBy('release_date')
            ->limit(4)
            ->get();

        $showtimes = Showtime::query()
            ->available()
            ->with(['movie', 'room.cinema'])
            ->where('starts_at', '<=', now()->addDays(7))
            ->orderBy('starts_at')
            ->limit(12)
            ->get();

        $cinemas = Cinema::query()->where('is_active', true)->withCount('rooms')->limit(6)->get();

        return view('welcome', compact('banners', 'featuredMovies', 'upcomingMovies', 'showtimes', 'cinemas'));
    }
}
