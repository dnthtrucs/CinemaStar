<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ManualRefundService
{
    public function resolve(Payment $payment, User $admin, string $note): void
    {
        DB::transaction(function () use ($payment, $admin, $note) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            abort_unless($payment->needsManualRefund() && $payment->refund_resolved_at === null, 422, 'Khoản tiền không cần hoàn thủ công hoặc đã được xử lý.');
            $payment->update([
                'refund_resolved_at' => now(), 'refund_resolved_by' => $admin->id,
                'refund_resolution_note' => $note,
            ]);
            ActivityLog::record('payment.manual_refund_resolved', 'Đã hoàn tiền thủ công cho giao dịch '.$payment->request_id, $payment, [
                'booking_id' => $payment->booking_id, 'transaction_id' => $payment->transaction_id,
                'amount' => $payment->amount, 'note' => $note,
            ]);
        });
    }
}
