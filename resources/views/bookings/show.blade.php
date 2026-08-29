@extends('layouts.app')

@section('title', 'Đơn '.$booking->code)

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <a href="{{ route('bookings.index') }}" class="text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i>Vé của tôi
            </a>

            <div class="card shadow-soft mt-3 overflow-hidden">
                <div class="p-4 p-md-5 {{ $booking->status === 'confirmed' ? 'bg-success' : 'bg-dark' }} text-white">
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
                        <div>
                            <div class="small text-white-50">MÃ ĐẶT VÉ</div>
                            <h2 class="fw-bold mb-0">{{ $booking->code }}</h2>
                        </div>
                        <div class="text-md-end">
                            <div class="small text-white-50">TRẠNG THÁI</div>
                            <div class="fs-5 fw-bold">
                                {{ match($booking->status) {'confirmed' => 'Đã thanh toán', 'pending' => 'Chờ thanh toán', 'cancelled' => 'Đã hủy', 'expired' => 'Đã hết hạn', default => $booking->status} }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-4 p-md-5">
                    <div class="row g-4">
                        <div class="col-md-8">
                            <h3 class="fw-bold">{{ $booking->showtime->movie->title }}</h3>
                            <div class="row g-3 mt-1">
                                <div class="col-sm-6">
                                    <div class="small text-muted">Thời gian</div>
                                    <strong>{{ $booking->showtime->starts_at->format('H:i · d/m/Y') }}</strong>
                                </div>
                                <div class="col-sm-6">
                                    <div class="small text-muted">Rạp / Phòng</div>
                                    <strong>{{ $booking->showtime->room->cinema->name }} / {{ $booking->showtime->room->name }}</strong>
                                </div>
                                <div class="col-sm-6">
                                    <div class="small text-muted">Ghế</div>
                                    <strong>{{ $booking->tickets->pluck('seat.label')->join(', ') ?: 'Đã giải phóng' }}</strong>
                                </div>
                                <div class="col-sm-6">
                                    <div class="small text-muted">Định dạng</div>
                                    <strong>{{ $booking->showtime->format }} · {{ $booking->showtime->language }}</strong>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 border-start-md">
                            <div class="small text-muted">Tổng thanh toán</div>
                            @if($booking->discount > 0)
                                <div class="small text-muted text-decoration-line-through">{{ number_format($booking->subtotal, 0, ',', '.') }}₫</div>
                                <div class="small text-success">Đã đổi {{ number_format($booking->points_used) }} điểm: -{{ number_format($booking->discount, 0, ',', '.') }}₫</div>
                            @endif
                            <div class="fs-3 fw-bold text-brand">{{ number_format($booking->total_price, 0, ',', '.') }}₫</div>
                            <div class="small text-muted">{{ $booking->quantity }} vé</div>
                            @if($booking->payment_status === 'paid')
                                <div class="small text-muted mt-3">Phương thức</div>
                                <strong>
                                    {{ match($booking->payment_method) {'momo' => 'Ví MoMo', 'vnpay' => 'VNPAY', 'sepay' => 'Chuyển khoản QR SePay', default => strtoupper((string) $booking->payment_method)} }}
                                </strong>
                            @endif
                        </div>
                    </div>

                    <hr class="my-4">

                    @if($booking->payment_status === 'paid' && $booking->points_earned > 0)
                        <div class="alert alert-light border mb-3"><i class="bi bi-stars text-warning me-2"></i>Bạn nhận được <strong>{{ number_format($booking->points_earned) }} điểm</strong> từ đơn này.</div>
                    @endif

                    @if($booking->isPayable())
                        <div class="alert alert-warning">
                            <i class="bi bi-hourglass-split me-2"></i>Đơn sẽ hết hạn lúc
                            <strong>{{ $booking->expires_at->format('H:i:s') }}</strong>.
                            Còn <span id="countdown">--:--</span>.
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('payments.show', $booking) }}" class="btn btn-primary btn-lg">Thanh toán ngay</a>
                            <form action="{{ route('bookings.cancel', $booking) }}" method="POST" onsubmit="return confirm('Bạn chắc chắn muốn hủy đơn?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-outline-danger btn-lg">Hủy đơn</button>
                            </form>
                        </div>
                    @elseif($booking->status === 'confirmed')
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            Vé đã sẵn sàng. Một mã QR dùng để check-in toàn bộ ghế trong đơn.
                        </div>

                        <article class="card border p-4 text-center ticket-qr-card mx-auto mt-4" style="max-width:460px">
                            <div class="small text-muted">MÃ QR ĐƠN VÉ</div>
                            <div class="fw-bold fs-4 text-brand mb-3">{{ $booking->code }}</div>
                            <div class="ticket-qr-wrap mx-auto">
                                <canvas class="ticket-qr" data-ticket-qr="{{ $bookingQrUrl }}" aria-label="Mã QR đơn {{ $booking->code }}"></canvas>
                                <div class="ticket-qr-loading small text-muted">Đang tạo mã QR...</div>
                            </div>
                            <div class="mt-3"><strong>Áp dụng cho ghế: {{ $booking->tickets->pluck('seat.label')->join(', ') }}</strong></div>
                            <div class="small text-muted mt-1">Nhân viên quét một lần để check-in toàn bộ ghế của đơn.</div>
                        </article>

                        <p class="small text-muted text-center mt-4 mb-0">
                            Không chia sẻ mã QR công khai. Mã QR chứa đường dẫn xác thực của đơn vé.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@if($booking->isPayable())
    @push('scripts')
        <script>
            const end = new Date('{{ $booking->expires_at->toIso8601String() }}').getTime();
            const el = document.getElementById('countdown');
            setInterval(() => {
                const distance = Math.max(0, end - Date.now());
                const minutes = Math.floor(distance / 60000);
                const seconds = Math.floor((distance % 60000) / 1000);
                el.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                if (!distance) location.reload();
            }, 1000);
        </script>
    @endpush
@endif
