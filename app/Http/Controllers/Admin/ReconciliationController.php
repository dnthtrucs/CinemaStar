<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentController;
use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\PaymentReconciliation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReconciliationController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['status' => ['nullable', 'in:attention,pending,resolved,all'], 'q' => ['nullable', 'string', 'max:100']]);
        $status = $data['status'] ?? 'attention';
        $query = Payment::with(['booking.showtime.room.cinema', 'reconciliation.resolver']);
        if ($status === 'attention') {
            $query->whereDoesntHave('reconciliation')->whereNull('refund_resolved_at')->where(fn ($q) => $q->whereIn('response_code', Payment::manualRefundCodes())->orWhere(fn ($q) => $q->where('status', 'success')->whereHas('booking.showtime', fn ($q) => $q->where('status', 'cancelled'))));
        }
        if ($status === 'pending') {
            $query->whereIn('status', ['pending', 'initiated']);
        }
        if ($status === 'resolved') {
            $query->where(fn ($q) => $q->whereHas('reconciliation')->orWhereNotNull('refund_resolved_at'));
        }
        if (! empty($data['q'])) {
            $query->where(fn ($q) => $q->where('transaction_id', $data['q'])->orWhere('request_id', $data['q'])->orWhereHas('booking', fn ($q) => $q->where('code', $data['q'])));
        }
        $payments = $query->latest('id')->paginate(20)->withQueryString();

        return view('admin.reconciliation.index', compact('payments', 'status'));
    }

    public function probe(Request $request, Payment $payment, PaymentController $controller)
    {
        $result = $controller->reconcileSepayPayment($payment);
        ActivityLog::record('payment.reconciled', 'Tra cứu ngân hàng cho '.$payment->request_id, $payment, ['result' => $result, 'status' => $payment->fresh()->status]);

        return back()->with($result === 'unavailable' ? 'error' : 'success', match ($result) {
            'unavailable' => 'Chưa truy cập được dữ liệu ngân hàng. Kiểm tra kết nối SePay.',
            'waiting' => 'Vừa kiểm tra gần đây. Vui lòng chờ 15 giây.',
            'not_needed' => 'Giao dịch này không cần tra cứu tự động.',
            default => 'Đã tra cứu ngân hàng. Trạng thái chỉ thay đổi khi tìm thấy giao dịch hợp lệ.',
        });
    }

    public function resolve(Request $request, Payment $payment)
    {
        $data = $request->validate(['bank_reference' => ['required', 'string', 'max:120'], 'note' => ['required', 'string', 'min:10', 'max:1000']]);
        DB::transaction(function () use ($payment, $data) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            abort_if($payment->reconciliation()->exists() || $payment->refund_resolved_at, 422, 'Khoản này đã được xử lý.');
            abort_unless($payment->needsManualRefund() || ($payment->status === 'success' && $payment->booking->showtime->status === 'cancelled'), 422, 'Chỉ ghi nhận xử lý khoản cần đối soát.');
            PaymentReconciliation::create($data + ['payment_id' => $payment->id, 'resolved_by' => auth()->id()]);
            ActivityLog::record('payment.reconciliation_resolved', 'Ghi nhận kết quả đối soát '.$payment->request_id, $payment, $data);
        });

        return back()->with('success', 'Đã lưu kết quả đối soát; không thay đổi số tiền hay tự xác nhận hoàn tiền.');
    }
}
