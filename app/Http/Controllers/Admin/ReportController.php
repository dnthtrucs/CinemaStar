<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    private function rows(Request $request)
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

        return $query->latest('paid_at')->get();
    }

    private function stored(CarbonImmutable $date): string
    {
        return $date->setTimezone(config('app.timezone', 'UTC'))->format('Y-m-d H:i:s');
    }

    public function revenueCsv(Request $r)
    {
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
