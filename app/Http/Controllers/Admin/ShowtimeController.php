<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShowtimeRequest;
use App\Models\Movie;
use App\Models\Room;
use App\Models\Showtime;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShowtimeController extends Controller
{
    public function index()
    {
        $showtimes = Showtime::with(['movie', 'room.cinema'])->orderByDesc('starts_at')->paginate(20);
        return view('admin.showtimes.index', compact('showtimes'));
    }

    public function create()
    {
        return view('admin.showtimes.form', [
            'showtime' => new Showtime(),
            'movies' => Movie::whereIn('status', ['now_showing', 'upcoming'])->orderBy('title')->get(),
            'rooms' => Room::where('is_active', true)->with('cinema')->get(),
        ]);
    }

    public function store(ShowtimeRequest $request)
    {
        Showtime::create($this->data($request));
        return redirect()->route('admin.showtimes.index')->with('success', 'Đã thêm suất chiếu.');
    }

    public function bulkCreate()
    {
        return view('admin.showtimes.bulk', [
            'movies' => Movie::whereIn('status', ['now_showing', 'upcoming'])->orderBy('title')->get(),
            'rooms' => Room::where('is_active', true)->with('cinema')->get(),
        ]);
    }

    public function bulkStore(Request $request)
    {
        $data = $request->validate([
            'movie_id' => ['required', 'exists:movies,id'],
            'room_id' => ['required', 'exists:rooms,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'times' => ['required', 'string', 'max:500'],
            'base_price' => ['required', 'integer', 'min:10000', 'max:2000000'],
            'format' => ['required', 'in:2D,3D,IMAX'],
            'language' => ['required', 'string', 'max:100'],
            'subtitle' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'in:scheduled,cancelled'],
        ]);

        $from = Carbon::parse($data['start_date'])->startOfDay();
        $to = Carbon::parse($data['end_date'])->startOfDay();

        if ($from->diffInDays($to) > 31) {
            throw ValidationException::withMessages(['end_date' => 'Chỉ được tạo tối đa 32 ngày trong một lần.']);
        }

        $times = collect(explode(',', $data['times']))
            ->map(fn ($time) => trim($time))
            ->filter()
            ->unique()
            ->values();

        foreach ($times as $time) {
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
                throw ValidationException::withMessages(['times' => "Giờ chiếu “{$time}” không đúng. Nhập theo dạng 09:00, 12:30, 19:45."]);
            }
        }

        if ($times->isEmpty()) {
            throw ValidationException::withMessages(['times' => 'Vui lòng nhập ít nhất một giờ chiếu.']);
        }

        $movie = Movie::findOrFail($data['movie_id']);
        $created = 0;
        $conflicts = [];
        $past = 0;

        DB::transaction(function () use ($data, $from, $to, $times, $movie, &$created, &$conflicts, &$past) {
            for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
                foreach ($times as $time) {
                    $startsAt = Carbon::parse($date->format('Y-m-d').' '.$time);

                    if ($startsAt->lte(now())) {
                        $past++;
                        continue;
                    }

                    $endsAt = $startsAt->copy()->addMinutes($movie->duration + 15);
                    $conflict = Showtime::query()
                        ->where('room_id', $data['room_id'])
                        ->where('status', '!=', 'cancelled')
                        ->where('starts_at', '<', $endsAt)
                        ->where('ends_at', '>', $startsAt)
                        ->exists();

                    if ($conflict) {
                        $conflicts[] = $startsAt->format('H:i d/m/Y');
                        continue;
                    }

                    Showtime::create([
                        'movie_id' => $data['movie_id'], 'room_id' => $data['room_id'],
                        'starts_at' => $startsAt, 'ends_at' => $endsAt,
                        'base_price' => $data['base_price'], 'format' => $data['format'],
                        'language' => $data['language'], 'subtitle' => $data['subtitle'] ?? null,
                        'status' => $data['status'],
                    ]);
                    $created++;
                }
            }
        });

        $message = "Đã tạo {$created} suất chiếu.";
        if ($conflicts) $message .= ' Bỏ qua '.count($conflicts).' suất trùng phòng: '.implode(', ', array_slice($conflicts, 0, 5)).(count($conflicts) > 5 ? '...' : '');
        if ($past) $message .= " Bỏ qua {$past} suất đã qua giờ hiện tại.";

        return redirect()->route('admin.showtimes.index')->with('success', $message);
    }

    public function edit(Showtime $showtime)
    {
        return view('admin.showtimes.form', ['showtime' => $showtime, 'movies' => Movie::orderBy('title')->get(), 'rooms' => Room::with('cinema')->get()]);
    }

    public function update(ShowtimeRequest $request, Showtime $showtime)
    {
        if ($showtime->bookings()->where('payment_status', 'paid')->exists()) return back()->with('error', 'Không thể sửa suất chiếu đã có khách thanh toán.');
        $showtime->update($this->data($request, $showtime));
        return redirect()->route('admin.showtimes.index')->with('success', 'Đã cập nhật suất chiếu.');
    }

    public function destroy(Showtime $showtime)
    {
        if ($showtime->bookings()->exists()) return back()->with('error', 'Không thể xóa suất chiếu đã phát sinh đơn. Hãy chuyển sang trạng thái hủy.');
        $showtime->delete();
        return back()->with('success', 'Đã xóa suất chiếu.');
    }

    private function data(ShowtimeRequest $request, ?Showtime $showtime = null): array
    {
        $data = $request->validated();
        $movie = Movie::findOrFail($data['movie_id']);
        $startsAt = Carbon::parse($data['starts_at']);
        $endsAt = $startsAt->copy()->addMinutes($movie->duration + 15);
        $conflict = Showtime::query()->where('room_id', $data['room_id'])->where('status', '!=', 'cancelled')->when($showtime, fn ($query) => $query->where('id', '!=', $showtime->id))->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt)->exists();
        if ($conflict) throw ValidationException::withMessages(['starts_at' => 'Phòng đã có suất chiếu trùng thời gian.']);
        $data['ends_at'] = $endsAt;
        return $data;
    }
}
