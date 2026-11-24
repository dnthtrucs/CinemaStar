<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Showtime;
use App\Services\BookingService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookingService) {}

    public function index(Request $request)
    {
        $this->bookingService->expirePendingBookings();

        $filter = $request->validate(['filter' => ['nullable', 'in:upcoming,unpaid,history']])['filter'] ?? null;
        $bookings = Booking::query()
            ->where('user_id', auth()->id())
            ->with(['showtime.movie', 'showtime.room.cinema', 'tickets.seat'])
            ->when($filter === 'upcoming', fn ($q) => $q->where('payment_status', 'paid')->where('status', 'confirmed')->whereHas('showtime', fn ($s) => $s->where('status', 'scheduled')->where('starts_at', '>', now())))
            ->when($filter === 'unpaid', fn ($q) => $q->where('status', 'pending')->where('payment_status', 'unpaid'))
            ->when($filter === 'history', fn ($q) => $q->where(fn ($nested) => $nested->whereIn('status', ['cancelled', 'expired'])->orWhere('payment_status', 'refunded')->orWhereHas('showtime', fn ($s) => $s->where('starts_at', '<=', now())->orWhere('status', '!=', 'scheduled'))))
            ->latest()
            ->paginate(10)->withQueryString();

        return view('bookings.index', compact('bookings'));
    }

    public function store(Request $request, Showtime $showtime)
    {
        $validated = $request->validate([
            'seats' => ['required', 'array', 'min:1', 'max:10'],
            'seats.*' => ['required', 'integer', 'distinct', 'exists:seats,id'],
            'voucher_code' => ['nullable', 'string', 'max:40'],
            'concessions' => ['sometimes', 'array'],
            'concessions.*' => ['integer', 'min:0', 'max:10'],
        ]);

        $booking = $this->bookingService->create($request->user(), $showtime, $validated['seats'], $validated['voucher_code'] ?? null, $validated['concessions'] ?? []);

        return redirect()->route('bookings.show', $booking)
            ->with('success', 'Giữ ghế thành công. Vui lòng thanh toán trước khi hết thời gian.');
    }

    public function show(Booking $booking)
    {
        $this->authorizeOwner($booking);
        $booking->load(['showtime.movie', 'showtime.room.cinema', 'tickets.seat', 'payments', 'concessions']);
        $bookingQrUrl = $booking->verificationUrl();

        return view('bookings.show', compact('booking', 'bookingQrUrl'));
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
