<form method="POST" action="{{ route('admin.manual-refunds.resolve', $payment) }}">
    @csrf @method('PATCH')
    <label for="refund-note-{{ $payment->id }}" class="form-label small">Ghi chú</label>
    <textarea id="refund-note-{{ $payment->id }}" name="note" class="form-control form-control-sm mb-2" required maxlength="1000" placeholder="Mã giao dịch hoàn tiền, ngân hàng…">{{ old('note') }}</textarea>
    <button class="btn btn-outline-success btn-sm">Đã hoàn tiền thủ công</button>
</form>
