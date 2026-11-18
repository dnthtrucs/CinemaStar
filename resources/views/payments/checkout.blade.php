@extends('layouts.app')

@section('title', 'Thanh toán')

@section('content')
<div class="container py-5 checkout-page">
    <nav class="checkout-steps mb-4" aria-label="Tiến trình đặt vé"><span><i class="bi bi-check-circle-fill"></i> Chọn ghế</span><span class="current" aria-current="step">2 · Thanh toán</span><span>3 · Nhận vé</span></nav>
    <div class="row justify-content-center">
        <div class="col-xl-10">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-2 mb-4">
                <div>
                    <a href="{{ route('bookings.show', $booking) }}" class="text-decoration-none small">
                        <i class="bi bi-arrow-left me-1"></i>Quay lại đơn hàng
                    </a>
                    <h1 class="section-title mt-2 mb-1">Hoàn tất đặt vé</h1>
                    <p class="text-muted mb-0">Mã đơn {{ $booking->code }}</p>
                </div>
                <span class="badge rounded-pill text-bg-light border p-2 px-3">Thanh toán đơn vé</span>
            </div>

            <div class="alert alert-light border small"><i class="bi bi-shield-check me-2"></i>Vé được phát hành sau khi hệ thống nhận xác nhận thanh toán thành công.</div>
            @unless(array_filter($providerStatus))
                <div class="alert alert-warning">Chưa có phương thức thanh toán khả dụng. Vui lòng liên hệ rạp để được hỗ trợ.</div>
            @endunless

            <div class="hold-notice mb-4" role="status"><div><i class="bi bi-hourglass-split me-2"></i>Thời gian giữ ghế còn lại <strong id="checkoutCountdown" class="ms-2">--:--</strong></div><span id="holdDescription" class="small">Hoàn tất thanh toán để nhận vé và mã QR.</span></div>
            <div class="row g-4">
                <div class="col-lg-7">
                    <form action="{{ route('payments.store', $booking) }}" method="POST" id="checkoutForm" class="card shadow-soft p-4 p-md-5">
                        @csrf
                        <h2 class="h5 fw-bold mb-1">Phương thức thanh toán</h2>
                        <p class="text-muted small mb-4">Chọn cách thanh toán phù hợp với bạn.</p>
                        @php
                            $defaultProvider = old('provider', $providerStatus['sepay'] ? 'sepay' : (array_key_first(array_filter($providerStatus)) ?? ''));
                        @endphp

                        @php
                            $availablePoints = (int) $booking->user->loyalty_points;
                            $membership = $booking->user->membershipTier();
                            // $booking->discount là số tiền voucher giảm.
                            $amountAfterVoucher = max(0, (int) $booking->subtotal - (int) $booking->discount);
                            $maxRedeemablePoints = min($availablePoints, (int) floor($amountAfterVoucher / 1000));
                            $usedPoints = (int) old('points_to_use', $booking->points_used);
                        @endphp
                        <div class="border rounded-4 p-3 mb-4 bg-light">
                            <p class="small fw-semibold">Hạng {{ $membership['label'] }} — tích điểm x{{ $membership['multiplier'] }}</p>
                            <div class="d-flex justify-content-between align-items-center gap-3">
                                <div>
                                    <strong><i class="bi bi-stars text-warning me-1"></i>Điểm thành viên</strong>
                                    <div class="small text-muted">Bạn đang có <strong>{{ number_format($availablePoints) }} điểm</strong> · 1 điểm = 1.000₫</div>
                                </div>
                                <span class="badge text-bg-dark">{{ number_format($availablePoints) }} điểm</span>
                            </div>
                            @if($booking->points_used > 0)
                                <div class="small text-success mt-2">Đơn này đã dùng {{ number_format($booking->points_used) }} điểm, giảm {{ number_format($booking->points_used * 1000, 0, ',', '.') }}₫.</div>
                                <input type="hidden" name="points_to_use" value="{{ $booking->points_used }}">
                            @elseif($maxRedeemablePoints > 0)
                                <label for="points_to_use" class="form-label small fw-semibold mt-3 mb-1">Số điểm muốn dùng</label>
                                <input id="points_to_use" class="form-control" type="number" name="points_to_use" min="0" max="{{ $maxRedeemablePoints }}" value="{{ $usedPoints }}">
                                <div class="form-text">Có thể dùng tối đa {{ number_format($maxRedeemablePoints) }} điểm, giảm tối đa {{ number_format($maxRedeemablePoints * 1000, 0, ',', '.') }}₫.</div>
                            @else
                                <div class="small text-muted mt-2">Bạn chưa có điểm để đổi cho đơn này.</div>
                            @endif
                            @error('points_to_use')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                        </div>

                        @if($providerStatus['momo'])
                        <label class="payment-option border rounded-4 p-3 mb-3 d-flex align-items-center gap-3 {{ $providerStatus['momo'] ? '' : 'opacity-50' }}">
                            <input class="form-check-input flex-shrink-0" type="radio" name="provider" value="momo"
                                {{ $defaultProvider === 'momo' ? 'checked' : '' }}
                                {{ $providerStatus['momo'] ? '' : 'disabled' }} required>
                            <span class="brand-mark flex-shrink-0" style="background:#a50064">M</span>
                            <span class="flex-grow-1">
                                <strong class="d-block">Ví MoMo</strong>
                                <span class="small text-muted">
                                    Chuyển đến ứng dụng hoặc website MoMo để thanh toán
                                </span>
                            </span>
                            <span class="badge text-bg-{{ $providerStatus['momo'] ? 'light' : 'secondary' }}">
                                {{ $providerStatus['momo'] ? ($paymentMode === 'sandbox' ? 'Thử nghiệm' : 'Khả dụng') : 'Tạm chưa khả dụng' }}
                            </span>
                        </label>
                        @endif

                        @if($providerStatus['vnpay'])
                        <label class="payment-option border rounded-4 p-3 mb-3 d-flex align-items-center gap-3 {{ $providerStatus['vnpay'] ? '' : 'opacity-50' }}">
                            <input class="form-check-input flex-shrink-0" type="radio" name="provider" value="vnpay"
                                {{ $defaultProvider === 'vnpay' ? 'checked' : '' }}
                                {{ $providerStatus['vnpay'] ? '' : 'disabled' }}>
                            <span class="brand-mark flex-shrink-0" style="background:#075eaa">V</span>
                            <span class="flex-grow-1">
                                <strong class="d-block">VNPAY</strong>
                                <span class="small text-muted">
                                    Chuyển đến QR/trang thanh toán VNPAY
                                </span>
                            </span>
                            <span class="badge text-bg-{{ $providerStatus['vnpay'] ? 'light' : 'secondary' }}">
                                {{ $providerStatus['vnpay'] ? ($paymentMode === 'sandbox' ? 'Thử nghiệm' : 'Khả dụng') : 'Tạm chưa khả dụng' }}
                            </span>
                        </label>
                        @endif

                        <label class="payment-option border rounded-4 p-3 mb-4 d-flex align-items-center gap-3 {{ $providerStatus['sepay'] ? '' : 'opacity-50' }}">
                            <input class="form-check-input flex-shrink-0" type="radio" name="provider" value="sepay"
                                {{ $defaultProvider === 'sepay' ? 'checked' : '' }}
                                {{ $providerStatus['sepay'] ? '' : 'disabled' }}>
                            <span class="brand-mark flex-shrink-0" style="background:#1261d8"><i class="bi bi-qr-code-scan"></i></span>
                            <span class="flex-grow-1">
                                <strong class="d-block">Chuyển khoản QR SePay</strong>
                                <span class="small text-muted">Quét QR bằng ứng dụng ngân hàng để chuyển khoản đúng số tiền</span>
                            </span>
                            <span class="badge text-bg-{{ $providerStatus['sepay'] ? 'light' : 'secondary' }}">
                                {{ $providerStatus['sepay'] ? 'QR ngân hàng' : 'Tạm chưa khả dụng' }}
                            </span>
                        </label>

                        @error('provider')
                            <div class="text-danger small mb-3">{{ $message }}</div>
                        @enderror

                        <button id="payButton" class="btn btn-primary btn-lg w-100" type="submit" @disabled(!array_filter($providerStatus))>
                            <i class="bi bi-lock me-2"></i><span id="payButtonText">Tiếp tục thanh toán</span>
                        </button>
                        <p class="small text-muted text-center mt-3 mb-0">
                            Bạn sẽ được chuyển đến bước thanh toán của phương thức đã chọn.
                        </p>
                    </form>
                </div>

                <div class="col-lg-5">
                    <aside class="card bg-dark text-white p-4 p-md-5 shadow-soft sticky-lg-top" style="top:92px">
                        <div class="small text-white-50">ĐƠN HÀNG</div>
                        <div class="d-flex gap-3 mt-3 mb-3">
                            @if($booking->showtime->movie->poster)<img src="{{ $booking->showtime->movie->poster }}" alt="Poster {{ $booking->showtime->movie->title }}" class="checkout-poster">@endif
                            <div><span class="badge bg-light text-dark mb-2">{{ $booking->showtime->movie->age_rating_label }} · {{ $booking->showtime->format }}</span><h4 class="fw-bold h5">{{ $booking->showtime->movie->title }}</h4><span class="small text-white-50">{{ $booking->showtime->movie->duration_label }}</span></div>
                        </div>
                        <div class="small text-white-50">{{ $booking->code }}</div>
                        <hr>
                        <div class="small text-white-50">Suất chiếu</div>
                        <div class="mb-3">{{ $booking->showtime->starts_at->format('H:i · d/m/Y') }}</div>
                        <div class="small text-white-50">Rạp / Phòng</div>
                        <div class="mb-3">{{ $booking->showtime->room->cinema->name }} / {{ $booking->showtime->room->name }}</div>
                        <div class="small text-white-50">Ghế đã chọn</div><div class="mb-3 fw-bold">{{ $booking->tickets->pluck('seat.label')->join(', ') }}</div>
                        @include('bookings.concessions')
                        <div class="d-flex justify-content-between"><span>Tạm tính</span><span>{{ number_format($booking->subtotal, 0, ',', '.') }}₫</span></div>
                        @if($booking->voucher && $booking->discount > 0)
                            <div class="d-flex justify-content-between text-success mt-2">
                                <span>Voucher {{ $booking->voucher->code }}</span>
                                <span>-{{ number_format($booking->discount, 0, ',', '.') }}₫</span>
                            </div>
                        @endif
                        <div class="d-flex justify-content-between text-warning mt-2 {{ $booking->points_used > 0 ? '' : 'd-none' }}" id="pointsDiscountRow"><span id="pointsDiscountLabel">Đổi điểm ({{ number_format($booking->points_used) }} điểm)</span><span id="pointsDiscountValue">-{{ number_format($booking->points_used * 1000, 0, ',', '.') }}₫</span></div>
                        <div class="d-flex justify-content-between align-items-end border-top pt-3 mt-3">
                            <span>{{ $booking->quantity }} vé</span>
                            <strong class="fs-4" id="checkoutTotal">{{ number_format($booking->total_price, 0, ',', '.') }}₫</strong>
                        </div>
                        <div class="small text-white-50 mt-4">
                            <i class="bi bi-lock-fill me-1"></i>
                            CinemaStar không lưu số thẻ hoặc mật khẩu ví của khách hàng.
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .checkout-steps { display:flex; justify-content:center; gap:clamp(12px,4vw,56px); color:#788293; font-size:13px; }
    .checkout-steps .current { color:#12365b; font-weight:800; }
    .hold-notice { display:flex; justify-content:space-between; flex-wrap:wrap; gap:10px; padding:18px 22px; border:1px solid #e9d6aa; background:#fff9e9; border-radius:14px; color:#654a0d; }
    #checkoutCountdown { font-variant-numeric:tabular-nums; font-size:20px; }
    .checkout-poster { width:75px; height:110px; object-fit:cover; border-radius:8px; }
    .checkout-page .card { border:1px solid #e5eaf0; }
    .payment-option:has(input:disabled) { cursor:not-allowed; }
    .checkout-page input[type=radio] { width:20px; height:20px; }
    .checkout-page aside { background:linear-gradient(145deg,#0a213d,#173d61)!important; }

    .payment-option { cursor:pointer; transition:.18s ease; background:#fff; }
    .payment-option:has(input:not(:disabled)):hover,
    .payment-option:has(input:checked) { border-color:var(--brand)!important; box-shadow:0 8px 24px rgba(23,23,23,.08); }
    .payment-option:has(input:checked) { background:#fff7f7; }
</style>
@endpush

@push('scripts')
<script>
    const pointsInput = document.getElementById('points_to_use');
    const maxPoints = {{ $maxRedeemablePoints ?? 0 }};
    // Điểm được tính sau khi voucher đã giảm giá.
    const amountAfterVoucher = {{ $amountAfterVoucher ?? max(0, (int) $booking->subtotal - (int) $booking->discount) }};
    const money = new Intl.NumberFormat('vi-VN');

    function updatePointsPreview() {
        if (!pointsInput) return;
        let points = Number.parseInt(pointsInput.value || '0', 10);
        points = Number.isFinite(points) ? Math.min(Math.max(points, 0), maxPoints) : 0;
        pointsInput.value = points;
        const discount = points * 1000;
        document.getElementById('pointsDiscountRow').classList.toggle('d-none', points === 0);
        document.getElementById('pointsDiscountLabel').textContent = `Đổi điểm (${money.format(points)} điểm)`;
        document.getElementById('pointsDiscountValue').textContent = `-${money.format(discount)}₫`;
        document.getElementById('checkoutTotal').textContent = `${money.format(amountAfterVoucher - discount)}₫`;
    }

    pointsInput?.addEventListener('input', updatePointsPreview);
    updatePointsPreview();
    const form = document.getElementById('checkoutForm');
    const payButton = document.getElementById('payButton');
    const buttonText = document.getElementById('payButtonText');
    let submitting = false;
    let expired = false;
    const providerNames = {momo:'MoMo', vnpay:'VNPAY', sepay:'QR ngân hàng'};
    function updateButton() {
        if (expired || submitting) return;
        const selected = form.querySelector('input[name="provider"]:checked');
        const total = document.getElementById('checkoutTotal').textContent;
        buttonText.textContent = selected ? `Thanh toán ${total} · ${providerNames[selected.value]}` : 'Chọn phương thức thanh toán';
        payButton.disabled = !selected;
    }
    form.querySelectorAll('input[name="provider"]').forEach(input => input.addEventListener('change', updateButton));
    pointsInput?.addEventListener('input', updateButton);
    const deadline = @json($booking->expires_at?->toIso8601String());
    const countdown = document.getElementById('checkoutCountdown');
    function tick() {
        if (!deadline) { countdown.textContent = 'Theo thời hạn đơn'; return; }
        const seconds = Math.max(0, Math.ceil((Date.parse(deadline) - Date.now()) / 1000));
        countdown.textContent = `${String(Math.floor(seconds/60)).padStart(2,'0')}:${String(seconds%60).padStart(2,'0')}`;
        if (!seconds) {
            expired = true;
            payButton.disabled = true;
            buttonText.textContent = 'Đã hết thời gian giữ ghế';
            document.getElementById('holdDescription').textContent = 'Quay lại đơn hàng để kiểm tra và đặt lại ghế.';
        }
    }
    tick();
    setInterval(tick, 1000);
    updateButton();
    form.addEventListener('submit', event => {
        if (submitting || expired) { event.preventDefault(); return; }
        submitting = true;
        payButton.disabled = true;
        buttonText.textContent = 'Đang tạo giao dịch…';
    });
    window.addEventListener('pageshow', () => { submitting = false; tick(); updateButton(); });
</script>
@endpush
