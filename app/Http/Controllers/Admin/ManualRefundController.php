<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\ManualRefundService;
use Illuminate\Http\Request;

class ManualRefundController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['status' => ['sometimes', 'in:pending,resolved,all']]);
        $status = $data['status'] ?? 'pending';
        $payments = Payment::with(['booking.user', 'refundResolver'])->whereIn('response_code', Payment::manualRefundCodes())
            ->when($status === 'pending', fn ($query) => $query->whereNull('refund_resolved_at'))
            ->when($status === 'resolved', fn ($query) => $query->whereNotNull('refund_resolved_at'))
            ->latest('id')->paginate(20)->withQueryString();

        return view('admin.manual-refunds.index', compact('payments', 'status'));
    }

    public function resolve(Request $request, Payment $payment, ManualRefundService $service)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:1000']]);
        $service->resolve($payment, $request->user(), $data['note']);

        return back()->with('success', 'Đã ghi nhận hoàn tiền thủ công.');
    }
}
