<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\LazyCollection;

class ReportController extends Controller
{
    private function query(Request $request)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
        ]);
        $now = CarbonImmutable::instance(now())->setTimezone('Asia/Ho_Chi_Minh');
        $from = isset($data['from'])
            ? CarbonImmutable::parse($data['from'], 'Asia/Ho_Chi_Minh')->startOfDay()
            : $now->subMonth()->startOfDay();
        $until = (isset($data['to'])
            ? CarbonImmutable::parse($data['to'], 'Asia/Ho_Chi_Minh')
            : $now)->startOfDay()->addDay();

        $query = Payment::with('booking.showtime.movie')->where('status', 'success')
            ->whereHas('booking', fn ($query) => $query->where('payment_status', 'paid'))
            ->where('paid_at', '>=', $this->stored($from));
        if ($until->greaterThan($now)) {
            $query->where('paid_at', '<=', $this->stored($now));
        } else {
            $query->where('paid_at', '<', $this->stored($until));
        }

        return $query->latest('paid_at')->orderByDesc('id');
    }

    private function stored(CarbonImmutable $date): string
    {
        return $date->setTimezone(config('app.timezone', 'UTC'))->format('Y-m-d H:i:s');
    }

    private function rows(Request $request): LazyCollection
    {
        $query = $this->query($request);

        return LazyCollection::make(function () use ($query) {
            $last = null;
            do {
                $batch = (clone $query)->when($last, function (Builder $query) use ($last) {
                    $paidAt = $last->getRawOriginal('paid_at');
                    $query->where(fn (Builder $after) => $after->where('paid_at', '<', $paidAt)
                        ->orWhere(fn (Builder $sameTime) => $sameTime->where('paid_at', $paidAt)->where('id', '<', $last->id)));
                })->limit(500)->get();

                foreach ($batch as $payment) {
                    yield $payment;
                }
                // Composite keyset pagination preserves the existing time order
                // when a concurrent refund removes a row from an earlier batch.
                $last = $batch->last();
            } while ($batch->count() === 500);
        });
    }

    public function revenueCsv(Request $r)
    {
        // Validate before sending headers, then hydrate bounded batches only as
        // the client consumes the stream. Eager loading also happens per batch.
        $rows = $this->rows($r);

        return response()->streamDownload(function () use ($rows) {
            $o = fopen('php://output', 'w');
            fprintf($o, "\xEF\xBB\xBF");
            fputcsv($o, ['Mã đơn', 'Phim', 'Phương thức', 'Số tiền', 'Thanh toán']);
            foreach ($rows as $p) {
                fputcsv($o, [$p->booking->code, $p->booking->showtime->movie->title, strtoupper($p->provider), $p->amount, $p->paid_at]);
            }
            fclose($o);
        }, 'doanh-thu.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function excel(Request $r)
    {
        $rows = $this->rows($r);

        return response()->view('admin.reports.excel', compact('rows'), 200, ['Content-Type' => 'application/vnd.ms-excel', 'Content-Disposition' => 'attachment; filename="doanh-thu.xls"']);
    }

    public function pdf(Request $r)
    {
        $rows = $this->rows($r);

        return response()->view('admin.reports.pdf', compact('rows'), 200, ['Content-Type' => 'text/html; charset=UTF-8', 'Content-Disposition' => 'inline; filename="doanh-thu.html"']);
    }
}
