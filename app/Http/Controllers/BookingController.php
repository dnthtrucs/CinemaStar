<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Showtime;
use App\Services\BookingService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookingService)
    {
    }

    public function index()
    {
        $this->bookingService->expirePendingBookings();

        $bookings = Booking::query()
            ->where('user_id', auth()->id())
            ->with(['showtime.movie', 'showtime.room.cinema', 'tickets.seat'])
            ->latest()
            ->paginate(10);

        return view('bookings.index', compact('bookings'));
    }

    public function store(Request $request, Showtime $showtime)
    {
        $validated = $request->validate([
            'seats' => ['required', 'array', 'min:1', 'max:10'],
            'seats.*' => ['required', 'integer', 'distinct', 'exists:seats,id'],
            'voucher_code' => ['nullable', 'string', 'max:40'],
        ]);

        $booking = $this->bookingService->create($request->user(), $showtime, $validated['seats'], $validated['voucher_code'] ?? null);

        return redirect()->route('bookings.show', $booking)
            ->with('success', 'Giữ ghế thành công. Vui lòng thanh toán trước khi hết thời gian.');
    }

    public function show(Booking $booking)
    {
        $this->authorizeOwner($booking);
        $booking->load(['showtime.movie', 'showtime.room.cinema', 'tickets.seat', 'payments']);

        return view('bookings.show', compact('booking'));
    }

    public function cancel(Booking $booking)
    {
        $this->authorizeOwner($booking);
        $this->bookingService->cancel($booking);

        return redirect()->route('bookings.index')->with('success', 'Đã hủy đơn và trả lại ghế.');
    }

    private function authorizeOwner(Booking $booking): void
    {
        abort_unless($booking->user_id === auth()->id() || auth()->user()->isAdmin(), 403);
    }
}
