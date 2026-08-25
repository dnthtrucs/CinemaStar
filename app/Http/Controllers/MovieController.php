<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use Illuminate\Http\Request;

class MovieController extends Controller
{
    public function index(Request $request)
    {
        $movies = Movie::query()
            ->when($request->filled('q'), fn ($query) => $query->where('title', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderByRaw("CASE WHEN status = 'now_showing' THEN 0 WHEN status = 'upcoming' THEN 1 ELSE 2 END")
            ->orderByDesc('release_date')
            ->paginate(12)
            ->withQueryString();

        return view('movies.index', compact('movies'));
    }

    public function show(Movie $movie)
    {
        $movie->load(['showtimes' => fn ($query) => $query
            ->available()
            ->with('room.cinema')
            ->orderBy('starts_at')]);

        return view('movies.show', compact('movie'));
    }
}
