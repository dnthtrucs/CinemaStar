<?php

namespace App\Http\Controllers;

use App\Models\Cinema;

class CinemaController extends Controller
{
    public function index()
    {
        $cinemas = Cinema::query()->where('is_active', true)->withCount('rooms')->paginate(12);
        return view('cinemas.index', compact('cinemas'));
    }

    public function show(Cinema $cinema)
    {
        $cinema->load(['rooms.showtimes' => fn ($query) => $query
            ->available()
            ->with('movie')
            ->orderBy('starts_at')]);

        return view('cinemas.show', compact('cinema'));
    }
}
