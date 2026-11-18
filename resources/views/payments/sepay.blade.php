@extends('layouts.app')
@section('title', 'Chuyển khoản thanh toán')
@section('content')
<div class="container py-5 bank-checkout">
    <a href="{{ route('bookings.show', $payment->booking_id) }}" class="small text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Quay lại đơn hàng</a>
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 my-4">
        <div><div class="small text-brand fw-bold mb-2">CINEMASTAR · THANH TOÁN</div><h1 class="section-title mb-2">Quét mã, hoàn tất đặt vé</h1><p class="text-muted mb-0">Mã đặt vé {{ $payment->booking->code }}</p></div>
        <div class="bank-deadline"><i class="bi bi-hourglass-split me-2"></i>Còn <strong id="bankCountdown">--:--</strong> để chuyển khoản</div>
    </div>
    <div class="row g-4">
        <div class="col-lg-6">
            <section class="card shadow-soft p-4 p-md-5 text-center h-100">
                <span class="small text-muted fw-bold">CHUYỂN KHOẢN QR NGÂN HÀNG</span>
                <div class="fs-2 fw-bold text-brand mt-2">{{ number_format($payment->amount, 0, ',', '.') }}₫</div>
                <div class="qr-frame mx-auto my-4" id="bankQrFrame"><img id="bankQr" src="{{ $paymentQrUrl }}" width="300" height="300" class="img-fluid" alt="QR chuyển khoản {{ $payment->request_id }}"><div id="qrLoadError" class="small text-danger d-none p-3">Chưa tải được mã QR. Bạn có thể chuyển khoản bằng thông tin bên cạnh.</div></div>
                <div id="paymentWaiting" role="status" aria-live="polite" class="payment-status"><span class="spinner-border spinner-border-sm me-2"></span>Đang chờ ngân hàng xác nhận</div>
                <p class="small text-muted mt-3 mb-0">Hệ thống tự cập nhật khi nhận xác nhận từ SePay.<br>Bạn không cần gửi ảnh biên lai.</p>
                <button type="button" id="checkPayment" class="btn btn-outline-primary mt-3">Kiểm tra giao dịch</button>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="card shadow-soft p-4 p-md-5 mb-4">
                <h2 class="h5 fw-bold mb-4">Thông tin chuyển khoản</h2>
                <div class="transfer-row"><span class="text-muted small">Ngân hàng</span><strong>{{ config('cinema.sepay.bank_code') }}</strong></div>
                @if(config('cinema.sepay.account_name'))<div class="transfer-row"><span class="text-muted small">Chủ tài khoản</span><strong>{{ config('cinema.sepay.account_name') }}</strong></div>@endif
                <div class="transfer-row"><span class="text-muted small">Số tài khoản</span><div class="d-flex align-items-center gap-2"><strong>{{ config('cinema.sepay.account_number') }}</strong><button class="copy-button" type="button" data-copy="{{ config('cinema.sepay.account_number') }}" aria-label="Sao chép số tài khoản"><i class="bi bi-copy"></i></button></div></div>
                <div class="transfer-row"><span class="text-muted small">Số tiền</span><div class="d-flex align-items-center gap-2"><strong>{{ number_format($payment->amount, 0, ',', '.') }}₫</strong><button class="copy-button" type="button" data-copy="{{ (int) $payment->amount }}" aria-label="Sao chép số tiền"><i class="bi bi-copy"></i></button></div></div>
                <div class="transfer-row"><span class="text-muted small">Nội dung</span><div class="d-flex align-items-center gap-2"><strong class="transfer-code">{{ $payment->request_id }}</strong><button class="copy-button" type="button" data-copy="{{ $payment->request_id }}" aria-label="Sao chép nội dung chuyển khoản"><i class="bi bi-copy"></i></button></div></div>
                <div id="copyFeedback" class="small text-success mt-2" role="status" aria-live="polite"></div>
                <p class="small text-muted mt-3 mb-0">Giữ nguyên nội dung và chuyển đúng số tiền để giao dịch được đối soát với đơn vé.</p>
            </section>
            <section class="card shadow-soft p-4">
                <h2 class="h6 fw-bold mb-3">Thanh toán trong 3 bước</h2>
                <ol class="small text-muted ps-3 mb-0"><li class="mb-2">Mở ứng dụng ngân hàng, chọn quét QR hoặc nhập thông tin chuyển khoản.</li><li class="mb-2">Kiểm tra người nhận, số tiền và nội dung trước khi xác nhận.</li><li>Quay lại đây, chờ xác nhận và nhận vé điện tử.</li></ol>
            </section>
        </div>
    </div>
</div>
@endsection
@push('styles')
<style>
.bank-checkout .card{border:1px solid #e3e9ef}.bank-deadline{padding:12px 18px;border-radius:12px;background:#fff6dd;color:#745213;font-size:13px}.bank-deadline strong{font-variant-numeric:tabular-nums;font-size:19px}.qr-frame{padding:12px;border:1px solid #e3e9ef;border-radius:20px;max-width:324px;background:#fff}.qr-frame img{display:block;width:100%;height:auto}.payment-status{padding:14px;border-radius:12px;background:#edf5ff;color:#12365b;font-size:14px}.transfer-row{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px 0;border-bottom:1px solid #edf0f4;overflow-wrap:anywhere}.transfer-row strong{text-align:right;font-size:14px}.transfer-code{font-family:monospace}.copy-button{border:0;background:#edf3fa;color:#173d61;border-radius:7px;padding:7px 9px;flex-shrink:0}.bank-checkout ol li{padding-left:6px;line-height:1.7}
</style>
@endpush
@push('scripts')
<script>
(() => {
    const statusUrl = @json(route('payments.sepay.status', $payment));
    const bookingUrl = @json(route('bookings.show', $payment->booking_id));
    const deadline = @json($payment->booking->expires_at?->toIso8601String());
    const waiting = document.getElementById('paymentWaiting');
    const check = document.getElementById('checkPayment');
    let stopped = false, checking = false, timer;
    function remaining() { return deadline ? Math.max(0, Math.ceil((Date.parse(deadline)-Date.now())/1000)) : null; }
    function finish(message) {
        stopped = true;
        clearTimeout(timer);
        waiting.textContent = message;
        waiting.classList.add('text-danger');
        document.getElementById('bankQrFrame').classList.add('d-none');
        check.textContent = 'Xem trạng thái đơn';
        check.disabled = false;
        check.onclick = () => location.assign(bookingUrl);
    }
    async function poll() {
        if (stopped || checking) return;
        checking = true;
        check.disabled = true;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 8000);
        try {
            const response = await fetch(statusUrl, {headers:{Accept:'application/json'}, signal:controller.signal, cache:'no-store'});
            if (response.status === 401 || response.status === 403) { finish('Phiên đăng nhập đã kết thúc. Vui lòng đăng nhập lại để kiểm tra đơn.'); return; }
            if (!response.ok) throw new Error('status');
            const result = await response.json();
            if (result.status === 'success') { stopped = true; waiting.textContent = 'Thanh toán thành công. Đang mở vé…'; location.assign(result.redirect_url); return; }
            if (['failed','cancelled'].includes(result.status)) { finish('Giao dịch chưa thành công. Vui lòng kiểm tra trạng thái đơn.'); return; }
            if (remaining() === 0) { finish('Đã hết hạn thanh toán. Không tiếp tục chuyển tiền vào mã QR này.'); return; }
            waiting.textContent = result.reconciliation === 'unavailable' ? 'Chưa kết nối được SePay để đối soát. Vui lòng không chuyển tiền lần nữa; hệ thống sẽ thử lại.' : 'Đang chờ ngân hàng xác nhận. Vé sẽ được cập nhật tự động.';
        } catch (_) {
            if (remaining() === 0) { finish('Đã hết hạn. Mở đơn hàng để kiểm tra kết quả; không tiếp tục chuyển tiền.'); return; }
            waiting.textContent = 'Chưa kết nối được để kiểm tra. Hệ thống sẽ thử lại; vui lòng không chuyển tiền lần nữa.';
        } finally {
            clearTimeout(timeout);
            checking = false;
            check.disabled = false;
            if (!stopped) { clearTimeout(timer); timer = setTimeout(poll, 4000); }
        }
    }
    function tick() {
        const seconds = remaining();
        document.getElementById('bankCountdown').textContent = seconds === null ? 'Theo thời hạn đơn' : `${String(Math.floor(seconds/60)).padStart(2,'0')}:${String(seconds%60).padStart(2,'0')}`;
        if (seconds === 0 && !stopped) { document.getElementById('bankQrFrame').classList.add('d-none'); poll(); }
    }
    check.onclick = poll;
    document.querySelectorAll('[data-copy]').forEach(button => button.addEventListener('click', async () => {
        const feedback = document.getElementById('copyFeedback');
        try { await navigator.clipboard.writeText(button.dataset.copy); feedback.textContent = 'Đã sao chép.'; }
        catch (_) { feedback.textContent = 'Không thể sao chép tự động. Vui lòng chọn và sao chép thông tin ở trên.'; }
    }));
    const qr = document.getElementById('bankQr');
    function qrError() { qr.classList.add('d-none'); document.getElementById('qrLoadError').classList.remove('d-none'); }
    qr.addEventListener('error', qrError);
    if (qr.complete && !qr.naturalWidth) qrError();
    tick(); setInterval(tick, 1000); poll();
})();
</script>
@endpush
