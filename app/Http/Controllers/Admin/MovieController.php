<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MovieRequest;
use App\Models\Movie;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MovieController extends Controller
{
    public function index()
    {
        $movies = Movie::latest()->paginate(15);

        return view('admin.movies.index', compact('movies'));
    }

    public function create()
    {
        return view('admin.movies.form', ['movie' => new Movie]);
    }

    public function store(MovieRequest $request)
    {
        $data = $this->data($request);
        Movie::create($data);

        return redirect()->route('admin.movies.index')->with('success', 'Đã thêm phim.');
    }

    public function edit(Movie $movie)
    {
        return view('admin.movies.form', compact('movie'));
    }

    public function update(MovieRequest $request, Movie $movie)
    {
        $movie->update($this->data($request));

        return redirect()->route('admin.movies.index')->with('success', 'Đã cập nhật phim.');
    }

    public function destroy(Movie $movie)
    {
        if ($movie->showtimes()->exists()) {
            return back()->with('error', 'Không thể xóa phim đã có suất chiếu. Hãy chuyển trạng thái sang ngừng chiếu.');
        }
        $movie->delete();

        return back()->with('success', 'Đã xóa phim.');
    }

    private function data(MovieRequest $request): array
    {
        $data = $request->validated();
        $data['duration'] = $data['duration'] ?? 0;
        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['title']).'-'.Str::lower(Str::random(4));
        $data['is_featured'] = $request->boolean('is_featured');
        $data['auto_schedule'] = $request->boolean('auto_schedule');
        if ($data['auto_schedule']) {
            $today = today()->toDateString();
            $data['status'] = ! empty($data['end_date']) && $data['end_date'] < $today
                ? 'stopped' : ($data['release_date'] > $today ? 'upcoming' : 'now_showing');
        }
        Cache::forget('home-page-data:v1');

        return $data;
    }
}
