<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\RefundRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RefundController extends Controller
{
    public function create(Booking $booking)
    {
        abort_unless($booking->user_id === auth()->id(), 403);
        if (! $booking->canRequestRefund()) {
            return redirect()->route('bookings.show', $booking)->with('error', 'Đơn vé không đủ điều kiện yêu cầu hoàn tiền hoặc đã có yêu cầu.');
        }

        return view('refunds.create', compact('booking'));
    }

    public function store(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === auth()->id(), 403);
        $data = $request->validate(['reason' => 'required|string|max:1000']);

        $created = DB::transaction(function () use ($booking, $data) {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            if (! $booking->canRequestRefund()) {
                return false;
            }

            $refund = RefundRequest::create([
                'booking_id' => $booking->id, 'requested_by' => auth()->id(),
                'reason' => $data['reason'], 'amount' => $booking->total_price,
            ]);
            $booking->update(['refund_status' => 'requested', 'refund_reason' => $data['reason'], 'refund_requested_at' => now()]);
            ActivityLog::record('refund.requested', "Khách yêu cầu hoàn tiền đơn {$booking->code}", $refund);

            return true;
        });

        return redirect()->route('bookings.show', $booking)->with(
            $created ? 'success' : 'error',
            $created ? 'Yêu cầu hoàn tiền đã được gửi.' : 'Đơn vé không đủ điều kiện yêu cầu hoàn tiền hoặc đã có yêu cầu.',
        );
    }
}
