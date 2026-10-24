<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\MovieReview;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\Ticket;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ActivityLogService
{
    public const GROUPS = [
        'payment' => 'Thanh toán',
        'refund' => 'Hoàn tiền',
        'ticket' => 'Vé',
        'booking' => 'Đơn vé',
        'review' => 'Đánh giá',
    ];

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = ActivityLog::with('user');
        if (! empty($filters['group'])) {
            $query->where('action', 'like', $filters['group'].'.%');
        }
        if (! empty($filters['actor'])) {
            $search = '%'.trim($filters['actor']).'%';
            $query->whereHas('user', fn ($users) => $users->where(function ($users) use ($search) {
                $users->where('name', 'like', $search)->orWhere('email', 'like', $search);
            }));
        }
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $this->dayBoundary($filters['from']));
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<', $this->dayBoundary($filters['to'], true));
        }

        return $query->latest('created_at')->latest('id')->paginate(30)->withQueryString();
    }

    public function relatedRecords(Collection $logs): array
    {
        $records = [];
        foreach ([Booking::class, Payment::class, Ticket::class, RefundRequest::class, MovieReview::class] as $class) {
            $morphClass = (new $class)->getMorphClass();
            $ids = $logs->where('subject_type', $morphClass)->pluck('subject_id')->filter()->unique();
            $query = $class::query();
            if (in_array($class, [Payment::class, Ticket::class, RefundRequest::class], true)) {
                $query->with('booking');
            }
            $records[$morphClass] = $ids->isEmpty() ? collect() : $query->whereIn('id', $ids)->get()->keyBy('id');
        }

        $related = [];
        foreach ($logs as $log) {
            $subject = ($records[$log->subject_type] ?? collect())->get($log->subject_id);
            $booking = match (true) {
                $subject instanceof Booking => $subject,
                $subject instanceof Payment, $subject instanceof Ticket, $subject instanceof RefundRequest => $subject->booking,
                default => null,
            };
            $related[$log->id] = [
                'label' => match (true) {
                    $subject instanceof Booking => 'Đơn vé '.$subject->code,
                    $subject instanceof Payment => 'Giao dịch '.$subject->request_id,
                    $subject instanceof Ticket => 'Vé '.$subject->code,
                    $subject instanceof RefundRequest => 'Yêu cầu hoàn tiền #'.$subject->id,
                    $subject instanceof MovieReview => 'Đánh giá #'.$subject->id,
                    default => $log->subject_id ? 'Đối tượng không còn tồn tại' : 'Không có đối tượng',
                },
                'booking' => $booking,
                'payment' => $subject instanceof Payment ? $subject : null,
                'review' => $subject instanceof MovieReview ? $subject : null,
            ];
        }

        return $related;
    }

    private function dayBoundary(string $day, bool $nextDay = false): string
    {
        $boundary = CarbonImmutable::parse($day, 'Asia/Ho_Chi_Minh')->startOfDay();

        return ($nextDay ? $boundary->addDay() : $boundary)
            ->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }
}
