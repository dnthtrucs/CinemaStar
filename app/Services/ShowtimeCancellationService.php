<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Showtime;
use App\Notifications\ShowtimeCancelledNotification;
use Illuminate\Support\Facades\DB;

class ShowtimeCancellationService
{
    public function cancel(array $ids, string $reason): int
    {
        return DB::transaction(function () use ($ids, $reason) {
            $showtimes = Showtime::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            abort_unless($showtimes->count() === count(array_unique($ids)), 422, 'Có suất chiếu không còn tồn tại.');
            foreach ($showtimes as $showtime) {
                abort_if($showtime->starts_at->lte(now()) && $showtime->status !== 'cancelled', 422, 'Chỉ hủy được suất chưa bắt đầu.');
                abort_if($showtime->tickets()->where(fn ($q) => $q->whereNotNull('checked_in_at')->orWhere('status', 'used'))->exists(), 422, 'Có vé đã check-in. Cần xử lý riêng với quản trị viên.');
            }
            $count = 0;
            foreach ($showtimes as $showtime) {
                if ($showtime->status === 'cancelled') {
                    continue;
                }
                $before = $showtime->only(['status', 'starts_at', 'room_id']);
                $showtime->update(['status' => 'cancelled']);
                foreach ($showtime->bookings()->orderBy('id')->lockForUpdate()->get() as $booking) {
                    if ($booking->status === 'pending' && $booking->payment_status === 'unpaid') {
                        app(BookingService::class)->cancel($booking);
                    }
                    if (! in_array($booking->status, ['pending', 'confirmed', 'cancelled'])) {
                        continue;
                    }
                    // Store the account notice inside the transaction; email is best effort after commit.
                    $booking->user->notify(new ShowtimeCancelledNotification($booking, $reason));
                    DB::afterCommit(function () use ($booking, $reason) {
                        try {
                            $booking->user->notify(new ShowtimeCancelledNotification($booking, $reason, true));
                        } catch (\Throwable $e) {
                            report($e);
                        }
                    });
                }
                ActivityLog::record('showtime.cancelled', 'Hủy suất chiếu '.$showtime->id, $showtime, ['reason' => $reason, 'before' => $before, 'after' => ['status' => 'cancelled']]);
                $count++;
            }

            return $count;
        }, 3);
    }
}
