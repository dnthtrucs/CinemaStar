@extends('layouts.app')
@section('title', 'Check-in vé')
@section('content')
@php($scanRoute = auth()->user()->isAdmin() ? route('admin.tickets.scan') : route('staff.tickets.scan'))
<div class="container py-4">
    @if(auth()->user()->isAdmin()) @include('admin.partials.nav') @endif
    <div class="row justify-content-center"><div class="col-lg-7">
        <h1 class="section-title">Check-in vé</h1>
        <p class="text-muted">Dùng camera điện thoại để quét QR trên vé, hoặc nhập mã đơn <strong>BK...</strong>.</p>

        <section class="card shadow-soft p-4 mb-4">
            <div class="d-flex align-items-center gap-3 mb-3">
                <span class="brand-mark"><i class="bi bi-qr-code-scan"></i></span>
                <div><h2 class="h5 fw-bold mb-1">Quét mã QR bằng camera</h2><p class="small text-muted mb-0">Mỗi mã QR là một vé; hệ thống chặn check-in lặp lại.</p></div>
            </div>
            <div id="qr-reader" class="rounded overflow-hidden d-none"></div>
            <div id="scanner-message" class="small text-muted mb-3">Bấm nút để bật camera sau của điện thoại.</div>
            <button id="start-scanner" class="btn btn-primary w-100" type="button"><i class="bi bi-camera me-2"></i>Mở camera quét QR</button>
            <form id="scan-form" method="POST" action="{{ $scanRoute }}" class="d-none">@csrf<input id="ticket-token" type="hidden" name="ticket_token"></form>
        </section>

        <div class="text-center text-muted small my-3"><span class="bg-body px-2">hoặc nhập mã đơn</span></div>
        <form class="card shadow-soft p-4 mb-4" method="GET">
            <div class="input-group input-group-lg"><input class="form-control text-uppercase" name="code" value="{{ request('code') }}" placeholder="Ví dụ: BK26081142KQQVFO" required><button class="btn btn-dark">Kiểm tra</button></div>
        </form>

        @if(request('code'))
            @if($booking)
                <div class="card shadow-soft p-4"><div class="d-flex justify-content-between align-items-start"><div><div class="small text-muted">MÃ ĐƠN / MÃ CHECK-IN</div><h3 class="fw-bold">{{ $booking->code }}</h3></div><span class="badge h-100 text-bg-{{ $booking->ticket_status_badge }}">{{ $booking->ticket_status_label }}</span></div><hr>
                <p><strong>Khách:</strong> {{ $booking->user->name }}</p><p><strong>Phim:</strong> {{ $booking->showtime->movie->title }}</p><p><strong>Suất:</strong> {{ $booking->showtime->starts_at->format('H:i d/m/Y') }}</p><p><strong>Ghế:</strong> {{ $booking->tickets->pluck('seat.label')->filter()->join(', ') }}</p>
                @if($booking->ticket_status === 'valid')<form method="POST" action="{{ auth()->user()->isAdmin() ? route('admin.tickets.update', $booking) : route('staff.tickets.update', $booking) }}">@csrf @method('PATCH')<button class="btn btn-primary btn-lg w-100">Xác nhận check-in toàn bộ ghế</button></form>
                @elseif($booking->ticket_status === 'checked_in')<div class="alert alert-success mb-0">Đơn đã check-in lúc {{ $booking->tickets->first()?->checked_in_at?->format('H:i · d/m/Y') }}.</div>
                @elseif($booking->ticket_status === 'expired')<div class="alert alert-secondary mb-0">Suất chiếu đã kết thúc. Vé chưa check-in đã hết hiệu lực.</div>
                @else<div class="alert alert-warning mb-0">Đơn này chưa đủ điều kiện để check-in.</div>@endif</div>
            @else <div class="alert alert-danger">Không tìm thấy mã đơn.</div>
            @endif
        @endif
    </div></div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/html5-qrcode" defer></script>
<script>
window.addEventListener('load', () => {
    const startButton = document.getElementById('start-scanner');
    const reader = document.getElementById('qr-reader');
    const message = document.getElementById('scanner-message');
    const form = document.getElementById('scan-form');
    const tokenInput = document.getElementById('ticket-token');
    let scanner;

    startButton?.addEventListener('click', async () => {
        if (!window.Html5Qrcode) {
            message.textContent = 'Không tải được trình quét QR. Hãy kiểm tra kết nối Internet rồi thử lại.';
            message.classList.add('text-danger');
            return;
        }

        startButton.disabled = true;
        reader.classList.remove('d-none');
        message.textContent = 'Đang khởi động camera...';
        scanner = new Html5Qrcode('qr-reader');

        try {
            await scanner.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 240, height: 240 } },
                async (decodedText) => {
                    await scanner.stop();
                    tokenInput.value = decodedText;
                    form.submit();
                },
                () => {},
            );
            message.textContent = 'Đưa mã QR vé vào khung hình để check-in.';
        } catch (error) {
            reader.classList.add('d-none');
            startButton.disabled = false;
            message.textContent = 'Không thể mở camera. Hãy cấp quyền camera; trên điện thoại cần truy cập bằng HTTPS hoặc localhost.';
            message.classList.add('text-danger');
        }
    });
});
</script>
@endpush
