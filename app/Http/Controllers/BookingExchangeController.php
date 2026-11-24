<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingExchange;
use App\Models\Showtime;
use App\Services\BookingExchangeService;
use App\Services\BookingService;
use Illuminate\Http\Request;

class BookingExchangeController extends Controller
{
    public function create(Request $request, Booking $booking, BookingExchangeService $service)
    {
        abort_unless($booking->user_id === $request->user()->id, 403);
        $eligible = $service->eligible($booking);
        $hasPending = BookingExchange::where('booking_id', $booking->id)->where('status', 'pending')->exists();
        $targets = ! $eligible || $hasPending ? collect() : Showtime::available()->where('movie_id', $booking->showtime->movie_id)->where('id', '!=', $booking->showtime_id)
            ->where('starts_at', '>', now()->addHour())->whereHas('room', fn ($q) => $q->where('cinema_id', $booking->showtime->room->cinema_id)->where('is_active', true))
            ->with('room')->orderBy('starts_at')->get();
        $target = $targets->firstWhere('id', (int) $request->query('target'));
        if ($target) {
            app(BookingService::class)->expirePendingBookings($target->id);
            $target->load('room.seats');
        }
        $requests = BookingExchange::with(['target.room', 'original.room', 'handler'])->where('booking_id', $booking->id)->latest()->get();

        return view('bookings.exchange', compact('booking', 'targets', 'target', 'requests', 'eligible', 'hasPending'));
    }

    public function store(Request $request, Booking $booking, BookingExchangeService $service)
    {
        abort_unless($booking->user_id === $request->user()->id, 403);
        $data = $request->validate(['target' => ['required', 'integer', 'exists:showtimes,id'], 'seats' => ['required', 'array', 'min:1', 'max:10'], 'seats.*' => ['required', 'integer', 'distinct'], 'reason' => ['required', 'string', 'max:1000']]);
        $service->request($booking, Showtime::findOrFail($data['target']), $data['seats'], $data['reason']);

        return back()->with('success', 'Đã gửi yêu cầu. Vé cũ vẫn giữ hiệu lực; ghế mới chưa được giữ cho đến khi quản trị xác nhận.');
    }

    public function withdraw(Request $request, BookingExchange $exchange, BookingExchangeService $service)
    {
        $service->withdraw($exchange, $request->user());

        return redirect()->route('exchanges.create', $exchange->booking_id)->with('success', 'Đã rút yêu cầu đổi suất. Vé của bạn vẫn giữ nguyên.');
    }

    public function index(Request $request)
    {
        $data = $request->validate(['status' => ['sometimes', 'in:pending,approved,rejected,withdrawn,all']]);
        $status = $data['status'] ?? 'pending';
        $counts = BookingExchange::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $exchanges = BookingExchange::with(['booking.user', 'target.room.seats', 'original.room', 'handler'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))->latest()->paginate(20)->withQueryString();

        return view('admin.exchanges', compact('exchanges', 'status', 'counts'));
    }

    public function resolve(Request $request, BookingExchange $exchange, BookingExchangeService $service)
    {
        $data = $request->validate(['decision' => ['required', 'in:approve,reject'], 'note' => ['required', 'string', 'min:5', 'max:1000'], 'reference' => ['nullable', 'string', 'max:100'], 'verified' => ['accepted_if:decision,approve']]);
        $service->resolve($exchange, $request->user(), $data['decision'] === 'approve', $data['note'], $data['reference'] ?? null);

        return back()->with('success', 'Đã xử lý yêu cầu đổi suất.');
    }
}
