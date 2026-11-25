<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Showtime;
use App\Services\ShowtimeCancellationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BulkCancellationController extends Controller
{
    public function preview(Request $request)
    {
        $data = $request->validate(['showtime_ids' => ['required', 'array', 'min:1', 'max:50'], 'showtime_ids.*' => ['integer', 'distinct', 'exists:showtimes,id'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        $showtimes = Showtime::with(['movie', 'room.cinema'])->withCount(['bookings', 'bookings as paid_count' => fn ($q) => $q->where('payment_status', 'paid')])->whereIn('id', $data['showtime_ids'])->get();
        $token = Str::random(40);
        $request->session()->put('cancel-preview', $data + ['token' => $token, 'expires' => now()->addMinutes(10)->timestamp]);

        return view('admin.showtimes.cancel-preview', compact('showtimes', 'token', 'data'));
    }

    public function store(Request $request, ShowtimeCancellationService $service)
    {
        $data = $request->session()->get('cancel-preview');
        abort_unless($data && is_string($request->input('token')) && hash_equals($data['token'], $request->input('token')) && $data['expires'] >= now()->timestamp, 422, 'Phiên xác nhận đã hết hạn. Hãy xem trước lại.');
        $count = $service->cancel($data['showtime_ids'], $data['reason']);
        $request->session()->forget('cancel-preview');

        return redirect()->route('admin.showtimes.index')->with('success', 'Đã hủy '.$count.' suất. Đơn đã trả tiền được đưa vào đối soát.');
    }
}
