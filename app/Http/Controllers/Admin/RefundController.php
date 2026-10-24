<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\RefundRequest;
use App\Notifications\RefundStatusNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class RefundController extends Controller
{
    public function index()
    {
        return view('admin.refunds.index', ['refunds' => RefundRequest::with('booking.user', 'requester')->latest()->paginate(20)]);
    }

    public function update(Request $request, RefundRequest $refund)
    {
        $data = $request->validate(['status' => 'required|in:approved,refunded,rejected', 'admin_note' => 'nullable|string|max:1000']);

        DB::transaction(function () use ($refund, $data) {
            $booking = Booking::query()->lockForUpdate()->findOrFail($refund->booking_id);
            $refund = RefundRequest::query()->lockForUpdate()->findOrFail($refund->id);
            $allowed = match ($refund->status) {
                'requested' => ['approved', 'rejected'],
                'approved' => ['refunded', 'rejected'],
                default => [],
            };
            abort_unless(in_array($data['status'], $allowed, true), 422, 'Trạng thái hoàn tiền không hợp lệ.');

            if ($data['status'] === 'refunded') {
                abort_unless($booking->status === 'confirmed' && $booking->payment_status === 'paid', 422, 'Đơn vé không đủ điều kiện hoàn tiền.');
                abort_if($booking->tickets()->where(fn ($q) => $q->where('status', 'used')->orWhereNotNull('checked_in_at'))->exists(), 422, 'Đơn vé đã check-in, không thể hoàn tiền.');

                $user = $booking->user()->lockForUpdate()->firstOrFail();
                $user->update([
                    'loyalty_points' => max(0, (int) $user->loyalty_points - (int) $booking->points_earned) + (int) $booking->points_used,
                    'lifetime_points' => max(0, $user->lifetimePoints() - (int) $booking->points_earned),
                ]);
                $booking->tickets()->delete();
                $booking->update([
                    'status' => 'cancelled', 'payment_status' => 'refunded',
                    'refunded_at' => now(), 'cancelled_at' => now(),
                ]);
            }

            $refund->update($data + ['handled_by' => auth()->id(), 'handled_at' => now()]);
            $booking->update(['refund_status' => $data['status']]);
            ActivityLog::record('refund.'.$data['status'], "Xử lý hoàn tiền đơn {$booking->code}", $refund);

            DB::afterCommit(function () use ($refund) {
                try {
                    $refund->requester->notify(new RefundStatusNotification($refund));
                } catch (Throwable $exception) {
                    report($exception);
                }
            });
        });

        return back()->with('success', 'Đã cập nhật yêu cầu hoàn tiền.');
    }
}
