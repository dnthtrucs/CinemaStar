<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingExchange;
use App\Models\Payment;
use App\Models\Showtime;
use App\Models\User;
use App\Notifications\PaymentReceiptNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class BookingExchangeService
{
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['exchange' => $message]);
    }

    public function eligible(Booking $booking): bool
    {
        return $booking->status === 'confirmed' && $booking->payment_status === 'paid'
            && $booking->showtime->status === 'scheduled' && $booking->showtime->starts_at->gte(now()->addHours(12))
            && ! $booking->refundRequest()->exists() && ! $booking->refund_status
            && $booking->tickets()->count() === $booking->quantity
            && ! $booking->tickets()->where(fn ($q) => $q->where('status', '!=', 'valid')->orWhereNotNull('checked_in_at'))->exists();
    }

    public function quote(Booking $booking, Showtime $target, array $ids): array
    {
        if (! $this->eligible($booking)) {
            $this->fail('Chỉ đổi vé đã thanh toán, chưa dùng, chưa yêu cầu hoàn và còn ít nhất 12 tiếng trước giờ chiếu.');
        }
        if ($target->id === $booking->showtime_id || $target->movie_id !== $booking->showtime->movie_id
            || $target->room->cinema_id !== $booking->showtime->room->cinema_id
            || ! $target->room->is_active || ! $target->room->cinema->is_active
            || $target->status !== 'scheduled' || $target->starts_at->lt(now()->addHours(12)) || $target->movie->status === 'stopped') {
            $this->fail('Suất mới phải cùng phim, cùng rạp, còn hoạt động và còn ít nhất 12 tiếng.');
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $seats = $target->room->seats()->where('is_active', true)->whereIn('id', $ids)->get();
        if ($seats->count() !== count($ids) || $seats->count() !== $booking->quantity) {
            $this->fail('Phải chọn đúng số vị trí của vé cũ.');
        }
        foreach ($seats->where('type', 'couple') as $seat) {
            $partner = $seat->number % 2 === 0 ? $seat->number - 1 : $seat->number + 1;
            if (! $seats->contains(fn ($s) => $s->row === $seat->row && $s->number === $partner && $s->type === 'couple')) {
                $this->fail('Cần chọn đủ cả cặp ghế đôi.');
            }
        }
        if ($target->tickets()->whereIn('seat_id', $ids)->exists()) {
            $this->fail('Ghế mới đã được giữ hoặc mua. Vui lòng chọn lại.');
        }
        $subtotal = (int) $seats->sum(fn ($s) => (int) $target->base_price + (int) $s->price_surcharge) + (int) $booking->concessions_total;
        $total = max(0, $subtotal - (int) $booking->discount - (int) $booking->points_used * 1000);

        return ['subtotal' => $subtotal, 'total' => $total, 'difference' => $total - (int) $booking->total_price, 'seats' => $seats];
    }

    public function request(Booking $booking, Showtime $target, array $ids, string $reason): BookingExchange
    {
        app(BookingService::class)->expirePendingBookings($target->id);
        $expectedShowtime = $booking->showtime_id;

        return DB::transaction(function () use ($booking, $target, $ids, $reason, $expectedShowtime) {
            Showtime::whereIn('id', [$booking->showtime_id, $target->id])->orderBy('id')->lockForUpdate()->get();
            $booking = Booking::lockForUpdate()->findOrFail($booking->id);
            if ($booking->showtime_id !== $expectedShowtime) {
                $this->fail('Vé vừa thay đổi. Vui lòng mở lại vé mới.');
            }
            if (BookingExchange::where('booking_id', $booking->id)->where('status', 'pending')->exists()) {
                $this->fail('Đơn đã có yêu cầu đổi đang chờ xử lý.');
            }
            $quote = $this->quote($booking, $target->fresh(), $ids);

            return BookingExchange::create([
                'booking_id' => $booking->id, 'from_showtime_id' => $booking->showtime_id, 'to_showtime_id' => $target->id,
                'seat_ids' => $quote['seats']->pluck('id')->all(), 'difference' => $quote['difference'], 'reason' => $reason,
                'snapshot' => ['code' => $booking->code, 'old_total' => (int) $booking->total_price, 'subtotal' => $quote['subtotal'], 'total' => $quote['total'],
                    'new_seats' => $quote['seats']->map(fn ($seat) => ['seat' => $seat->label, 'price' => (int) $target->base_price + (int) $seat->price_surcharge])->all(),
                    'from_time' => $booking->showtime->starts_at->format('H:i d/m/Y'), 'to_time' => $target->starts_at->format('H:i d/m/Y'),
                    'old_seats' => $booking->tickets()->with('seat')->get()->map(fn ($t) => ['seat' => $t->seat->label, 'price' => $t->unit_price])->all()],
            ]);
        });
    }

    public function withdraw(BookingExchange $exchange, User $actor): void
    {
        DB::transaction(function () use ($exchange, $actor) {
            $booking = Booking::lockForUpdate()->findOrFail($exchange->booking_id);
            abort_unless($booking->user_id === $actor->id, 403);
            $exchange = BookingExchange::lockForUpdate()->findOrFail($exchange->id);
            if ($exchange->status !== 'pending') {
                $this->fail('Chỉ có thể rút yêu cầu đang chờ xử lý.');
            }
            $exchange->update(['status' => 'withdrawn', 'resolution' => 'Khách đã rút yêu cầu; vé hiện tại không thay đổi.', 'handled_by' => $actor->id, 'handled_at' => now()]);
            ActivityLog::record('booking.exchange.withdrawn', 'Khách rút yêu cầu đổi suất', $booking, ['exchange_id' => $exchange->id]);
        });
    }

    public function resolve(BookingExchange $exchange, User $actor, bool $approve, string $note, ?string $reference): void
    {
        abort_unless($actor->isAdmin(), 403);
        app(BookingService::class)->expirePendingBookings($exchange->to_showtime_id);
        DB::transaction(function () use ($exchange, $actor, $approve, $note, $reference) {
            Showtime::whereIn('id', [$exchange->from_showtime_id, $exchange->to_showtime_id])->orderBy('id')->lockForUpdate()->get();
            $booking = Booking::lockForUpdate()->findOrFail($exchange->booking_id);
            $exchange = BookingExchange::lockForUpdate()->findOrFail($exchange->id);
            if ($exchange->status !== 'pending') {
                $this->fail('Yêu cầu đã được xử lý.');
            }
            if ($approve) {
                $quote = $this->quote($booking, Showtime::findOrFail($exchange->to_showtime_id), $exchange->seat_ids);
                if ($booking->code !== $exchange->snapshot['code'] || (int) $booking->total_price !== $exchange->snapshot['old_total']
                    || $quote['total'] !== $exchange->snapshot['total']) {
                    $this->fail('Giá hoặc vé đã thay đổi; từ chối yêu cầu và lập lại báo giá.');
                }
                if ($quote['difference'] !== 0) {
                    if (! $reference || mb_strlen(trim($reference)) < 6) {
                        $this->fail('Cần mã chứng từ thu bù/hoàn tiền đã được kiểm tra thực tế.');
                    }
                    if (Payment::where('transaction_id', trim($reference))->exists()) {
                        $this->fail('Chứng từ này đã được ghi nhận.');
                    }
                    Payment::create(['booking_id' => $booking->id, 'provider' => 'exchange_adjustment', 'request_id' => 'EX'.Str::uuid(),
                        'transaction_id' => trim($reference), 'amount' => $quote['difference'], 'status' => 'success', 'paid_at' => now(),
                        'response_code' => 'MANUAL_VERIFIED', 'payload' => ['exchange_id' => $exchange->id, 'verified_by' => $actor->id, 'note' => $note]]);
                }
                $booking->tickets()->delete();
                foreach ($quote['seats'] as $seat) {
                    $booking->tickets()->create([
                        'showtime_id' => $exchange->to_showtime_id, 'seat_id' => $seat->id, 'code' => 'TK'.Str::upper(Str::random(16)),
                        'unit_price' => (int) $exchange->target->base_price + (int) $seat->price_surcharge, 'qr_token' => hash('sha256', (string) Str::uuid()), 'status' => 'valid',
                    ]);
                }
                $booking->update(['showtime_id' => $exchange->to_showtime_id, 'subtotal' => $quote['subtotal'], 'total_price' => $quote['total'],
                    'code' => 'BK'.now()->format('ymd').Str::upper(Str::random(8))]);
            }
            $exchange->update(['status' => $approve ? 'approved' : 'rejected', 'resolution' => $note, 'handled_by' => $actor->id, 'handled_at' => now()]);
            $booking->user->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'booking.exchange', 'data' => [
                'title' => $approve ? 'Đổi suất thành công — mở vé mới' : 'Yêu cầu đổi suất chưa được chấp nhận',
                'message' => $approve ? 'Vé cũ đã hết hiệu lực. Vui lòng mở mã vé mới trong tài khoản.' : $note, 'booking_id' => $booking->id]]);
            ActivityLog::record('booking.exchange.'.$exchange->status, 'Xử lý đổi suất', $booking, ['exchange_id' => $exchange->id, 'difference' => $exchange->difference, 'reference' => $reference]);
            if ($approve) {
                $bookingId = $booking->id;
                $newCode = $booking->code;
                $previousCode = $exchange->snapshot['code'];
                $exchangeId = $exchange->id;
                DB::afterCommit(function () use ($bookingId, $newCode, $previousCode, $exchangeId) {
                    $current = Booking::with(['user', 'showtime.movie', 'showtime.room.cinema', 'tickets.seat', 'concessions'])->find($bookingId);
                    if (! $current || $current->code !== $newCode || $current->payment_status !== 'paid') {
                        return;
                    }
                    try {
                        if (in_array(config('mail.default'), ['log', 'array'], true)) {
                            ActivityLog::record('booking.exchange.email_unconfigured', 'Chưa cấu hình gửi email vé mới sau đổi suất', $current, ['exchange_id' => $exchangeId]);

                            return;
                        }
                        $current->user->notify(new PaymentReceiptNotification($current, $previousCode));
                        ActivityLog::record('booking.exchange.email_sent', 'Dịch vụ email đã nhận vé mới sau đổi suất', $current, ['exchange_id' => $exchangeId]);
                    } catch (Throwable $exception) {
                        report($exception);
                        ActivityLog::record('booking.exchange.email_failed', 'Chưa gửi được email vé mới; có thể gửi lại từ chi tiết đơn', $current, ['exchange_id' => $exchangeId]);
                    }
                });
            }
        });
    }
}
