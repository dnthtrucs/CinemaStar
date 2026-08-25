<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CinemaRequest;
use App\Models\Cinema;
use Illuminate\Support\Str;

class CinemaController extends Controller
{
    public function index()
    {
        $cinemas = Cinema::withCount('rooms')->latest()->paginate(15);
        return view('admin.cinemas.index', compact('cinemas'));
    }

    public function create()
    {
        return view('admin.cinemas.form', ['cinema' => new Cinema()]);
    }

    public function store(CinemaRequest $request)
    {
        Cinema::create($this->data($request));
        return redirect()->route('admin.cinemas.index')->with('success', 'Đã thêm rạp.');
    }

    public function edit(Cinema $cinema)
    {
        return view('admin.cinemas.form', compact('cinema'));
    }

    public function update(CinemaRequest $request, Cinema $cinema)
    {
        $cinema->update($this->data($request));
        return redirect()->route('admin.cinemas.index')->with('success', 'Đã cập nhật rạp.');
    }

    public function destroy(Cinema $cinema)
    {
        if ($cinema->rooms()->whereHas('showtimes')->exists()) {
            return back()->with('error', 'Không thể xóa rạp đã có lịch chiếu.');
        }
        $cinema->delete();
        return back()->with('success', 'Đã xóa rạp.');
    }

    private function data(CinemaRequest $request): array
    {
        $data = $request->validated();
        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['name']).'-'.Str::lower(Str::random(4));
        $data['is_active'] = $request->boolean('is_active');
        return $data;
    }
}
