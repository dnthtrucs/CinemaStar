<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Concession;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function create(User $user, Showtime $showtime, array $seatIds, ?string $voucherCode = null, array $concessions = []): Booking
    {
        $this->expirePendingBookings($showtime->id);

        Validator::make(['concessions' => $concessions], [
            'concessions' => ['array'], 'concessions.*' => ['integer', 'min:0', 'max:10'],
        ], ['concessions.*' => 'Mỗi combo được chọn từ 0 đến 10 phần, tối đa 5 loại.'])->validate();
        $concessions = array_filter($concessions, fn ($quantity) => (int) $quantity > 0);
        Validator::make(['concessions' => $concessions], [
            'concessions' => ['max:5'],
        ], ['concessions.max' => 'Mỗi đơn được chọn tối đa 5 loại combo.'])->validate();
        foreach (array_keys($concessions) as $id) {
            if (! ctype_digit((string) $id) || (int) $id < 1 || (string) (int) $id !== (string) $id) {
                throw ValidationException::withMessages(['concessions' => 'Mã combo không hợp lệ.']);
            }
        }

        return DB::transaction(function () use ($user, $showtime, $seatIds, $voucherCode, $concessions) {
            $showtime = Showtime::query()->lockForUpdate()->findOrFail($showtime->id);

            if ($showtime->status !== 'scheduled' || $showtime->starts_at->isPast()) {
                throw ValidationException::withMessages([
                    'seats' => 'Suất chiếu đã bắt đầu hoặc không còn nhận đặt vé.',
                ]);
            }

            $requestedSeatIds = array_values(array_unique($seatIds));
            $seats = Seat::query()
                ->where('room_id', $showtime->room_id)
                ->where('is_active', true)
                ->whereIn('id', $requestedSeatIds)
                ->lockForUpdate()
                ->get();

            if ($seats->count() !== count($requestedSeatIds)) {
                throw ValidationException::withMessages([
                    'seats' => 'Danh sách ghế không hợp lệ.',
                ]);
            }

            $partnerNumbers = $seats->where('type', 'couple')
                ->map(fn (Seat $seat) => [
                    'row' => $seat->row,
                    'number' => $seat->number % 2 === 0 ? $seat->number - 1 : $seat->number + 1,
                ]);

            if ($partnerNumbers->isNotEmpty()) {
                $partnerSeats = Seat::query()
                    ->where('room_id', $showtime->room_id)
                    ->where('is_active', true)
                    ->where('type', 'couple')
                    ->where(function ($query) use ($partnerNumbers) {
                        foreach ($partnerNumbers as $partner) {
                            $query->orWhere(fn ($pair) => $pair
                                ->where('row', $partner['row'])
                                ->where('number', $partner['number']));
                        }
                    })
                    ->lockForUpdate()
                    ->get();

                if ($partnerSeats->count() !== $partnerNumbers->unique(fn ($seat) => $seat['row'].'-'.$seat['number'])->count()) {
                    throw ValidationException::withMessages([
                        'seats' => 'Ghế đôi không đầy đủ hoặc không còn khả dụng.',
                    ]);
                }

                $seats = $seats->merge($partnerSeats)->unique('id')->values();
            }

            if ($seats->count() > 10) {
                throw ValidationException::withMessages([
                    'seats' => 'Mỗi đơn được chọn tối đa 10 vị trí.',
                ]);
            }

            $bookedSeatIds = Ticket::query()
                ->where('showtime_id', $showtime->id)
                ->whereIn('seat_id', $seats->pluck('id'))
                ->pluck('seat_id');

            if ($bookedSeatIds->isNotEmpty()) {
                $labels = $seats->whereIn('id', $bookedSeatIds)->pluck('label')->join(', ');
                throw ValidationException::withMessages([
                    'seats' => "Ghế {$labels} vừa được người khác chọn. Vui lòng chọn ghế khác.",
                ]);
            }

            // Price snapshots go into the pivot; shared locks allow parallel
            // bookings while preventing catalogue edits until this read commits.
            $items = Concession::query()->whereIn('id', array_keys($concessions))->where('is_active', true)
                ->orderBy('id')->sharedLock()->get();
            if ($items->count() !== count($concessions)) {
                throw ValidationException::withMessages(['concessions' => 'Combo đã ngừng bán hoặc không tồn tại. Vui lòng chọn lại.']);
            }
            $concessionsTotal = $items->sum(fn (Concession $item) => $item->price * $concessions[$item->id]);
            $subtotal = $seats->sum(fn (Seat $seat) => (float) $showtime->base_price + (float) $seat->price_surcharge) + $concessionsTotal;
            $voucher = null;
            if ($voucherCode) {
                $voucher = Voucher::query()->where('code', strtoupper(trim($voucherCode)))->lockForUpdate()->first();
                if (! $voucher || ! $voucher->isUsableFor((int) $subtotal, $user)) {
                    throw ValidationException::withMessages(['voucher_code' => 'Mã giảm giá không hợp lệ hoặc không áp dụng cho đơn này.']);
                }
            }
            $discount = $voucher ? $voucher->discountFor((int) $subtotal) : 0;

            $booking = Booking::create([
                'user_id' => $user->id,
                'showtime_id' => $showtime->id,
                'code' => $this->uniqueCode('BK', Booking::class),
                'quantity' => $seats->count(),
                'subtotal' => $subtotal,
                'concessions_total' => $concessionsTotal,
                'voucher_id' => $voucher?->id,
                'discount' => $discount,
                'total_price' => $subtotal - $discount,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'expires_at' => now()->addMinutes(config('cinema.booking_hold_minutes', 10)),
            ]);

            foreach ($items as $item) {
                $booking->concessions()->attach($item->id, ['name' => $item->name, 'quantity' => $concessions[$item->id], 'unit_price' => $item->price]);
            }

            foreach ($seats as $seat) {
                Ticket::create([
                    'booking_id' => $booking->id,
                    'showtime_id' => $showtime->id,
                    'seat_id' => $seat->id,
                    'code' => $this->uniqueCode('TK', Ticket::class),
                    'unit_price' => (float) $showtime->base_price + (float) $seat->price_surcharge,
                    'qr_token' => hash('sha256', Str::uuid()->toString()),
                    'status' => 'valid',
                ]);
            }

            return $booking->load('tickets.seat', 'showtime.movie', 'showtime.room.cinema', 'concessions');
        });
    }

    public function cancel(Booking $booking): void
    {
        DB::transaction(function () use ($booking) {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if (! $booking->isCancellable()) {
                throw ValidationException::withMessages([
                    'booking' => 'Chỉ có thể hủy đơn chưa thanh toán.',
                ]);
            }

            $this->refundReservedPoints($booking);
            $booking->tickets()->delete();
            $booking->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);
        });
    }

    public function expirePendingBookings(?int $showtimeId = null): int
    {
        $query = Booking::query()
            ->where('status', 'pending')
            ->where('payment_status', 'unpaid')
            ->where('expires_at', '<=', now());

        if ($showtimeId) {
            $query->where('showtime_id', $showtimeId);
        }

        $expiredCount = 0;
        foreach ($query->pluck('id') as $bookingId) {
            $expiredCount += (int) $this->expirePendingBooking($bookingId);
        }

        return $expiredCount;
    }

    public function expirePendingBooking(int $bookingId): bool
    {
        return DB::transaction(function () use ($bookingId) {
            $booking = Booking::query()->lockForUpdate()->find($bookingId);
            if (! $booking || $booking->status !== 'pending' || $booking->payment_status !== 'unpaid'
                || ! $booking->expires_at || $booking->expires_at->isFuture()) {
                return false;
            }

            $this->refundReservedPoints($booking);
            $booking->tickets()->delete();
            $booking->update(['status' => 'expired']);

            return true;
        });
    }

    private function uniqueCode(string $prefix, string $model): string
    {
        do {
            $code = $prefix.now()->format('ymd').Str::upper(Str::random(8));
        } while ($model::query()->where('code', $code)->exists());

        return $code;
    }

    /** Hoàn điểm đã giữ, còn ưu đãi voucher của đơn vẫn được giữ nguyên. */
    private function refundReservedPoints(Booking $booking): void
    {
        if ((int) $booking->points_used < 1) {
            return;
        }

        $booking->user()->lockForUpdate()->firstOrFail()->increment('loyalty_points', $booking->points_used);
        $booking->update([
            'points_used' => 0,
            'total_price' => max(0, (int) $booking->subtotal - (int) $booking->discount),
        ]);
    }
}
