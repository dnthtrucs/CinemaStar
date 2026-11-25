<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    public function create(Booking $booking)
    {
        abort_unless($booking->user_id === auth()->id(), 403);

        return redirect()->route('bookings.show', $booking)->with('error', 'Chức năng yêu cầu hoàn tiền đã ngừng. Liên hệ rạp nếu có sai lệch thanh toán.');
    }

    public function store(Request $request, Booking $booking)
    {
        return $this->create($booking);
    }
}
