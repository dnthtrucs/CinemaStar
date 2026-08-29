@extends('layouts.app')

@section('title', 'Thanh toán')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-xl-10">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-2 mb-4">
                <div>
                    <a href="{{ route('bookings.show', $booking) }}" class="text-decoration-none small">
                        <i class="bi bi-arrow-left me-1"></i>Quay lại đơn hàng
                    </a>
                    <h1 class="section-title mt-2 mb-1">Chọn phương thức thanh toán</h1>
                    <p class="text-muted mb-0">Mã đơn {{ $booking->code }}</p>
                </div>
                <span class="badge rounded-pill text-bg-{{ $paymentMode === 'simulate' ? 'warning' : 'success' }} p-2 px-3">
                    {{ $paymentMode === 'production' ? 'Thanh toán trực tuyến' : 'Quét QR hoặc xác nhận trên ứng dụng' }}
                </span>
            </div>

            @if($paymentMode === 'simulate')
                <div class="alert alert-info d-flex gap-2 align-items-start">
                    <i class="bi bi-info-circle-fill mt-1"></i>
                    <div>
                        <strong>Đang dùng chế độ trình diễn đồ án.</strong>
                        Bạn có thể chọn MoMo hoặc VNPAY và xác nhận kết quả mà không phát sinh giao dịch thật.
                    </div>
                </div>
            @else
                <div class="alert alert-success d-flex gap-2 align-items-start">
                    <i class="bi bi-shield-check mt-1"></i>
                    <div>
                        Giao dịch được chuyển sang trang thanh toán chính thức của nhà cung cấp.
                        Kết quả chỉ được ghi nhận sau khi hệ thống kiểm tra chữ ký, số tiền và mã đơn.
                    </div>
                </div>
            @endif

            <div class="row g-4">
                <div class="col-lg-7">
                    <form action="{{ route('payments.store', $booking) }}" method="POST" class="card shadow-soft p-4 p-md-5">
                        @csrf

                        @php
                            $availablePoints = (int) $booking->user->loyalty_points;
                            // $booking->discount là số tiền voucher giảm.
                            $amountAfterVoucher = max(0, (int) $booking->subtotal - (int) $booking->discount);
                            $maxRedeemablePoints = min($availablePoints, (int) floor($amountAfterVoucher / 1000));
                            $usedPoints = (int) old('points_to_use', $booking->points_used);
                        @endphp
                        <div class="border rounded-4 p-3 mb-4 bg-light">
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

                        <label class="payment-option border rounded-4 p-3 mb-3 d-flex align-items-center gap-3 {{ $providerStatus['momo'] ? '' : 'opacity-50' }}">
                            <input class="form-check-input flex-shrink-0" type="radio" name="provider" value="momo"
                                {{ old('provider') === 'momo' ? 'checked' : '' }}
                                {{ $providerStatus['momo'] ? '' : 'disabled' }} required>
                            <span class="brand-mark flex-shrink-0" style="background:#a50064">M</span>
                            <span class="flex-grow-1">
                                <strong class="d-block">Ví MoMo</strong>
                                <span class="small text-muted">
                                    {{ $paymentMode === 'production' ? 'Chuyển đến ứng dụng hoặc website MoMo để thanh toán' : 'Quét QR hoặc xác nhận trên ứng dụng MoMo' }}
                                </span>
                            </span>
                            <span class="badge text-bg-{{ $providerStatus['momo'] ? 'light' : 'secondary' }}">
                                {{ $providerStatus['momo'] ? 'Sẵn sàng' : 'Chưa cấu hình' }}
                            </span>
                        </label>

                        <label class="payment-option border rounded-4 p-3 mb-3 d-flex align-items-center gap-3 {{ $providerStatus['vnpay'] ? '' : 'opacity-50' }}">
                            <input class="form-check-input flex-shrink-0" type="radio" name="provider" value="vnpay"
                                {{ old('provider') === 'vnpay' ? 'checked' : '' }}
                                {{ $providerStatus['vnpay'] ? '' : 'disabled' }}>
                            <span class="brand-mark flex-shrink-0" style="background:#075eaa">V</span>
                            <span class="flex-grow-1">
                                <strong class="d-block">VNPAY</strong>
                                <span class="small text-muted">
                                    {{ $paymentMode === 'production' ? 'Chuyển đến QR/trang thanh toán VNPAY' : 'QR, ATM nội địa và thẻ quốc tế' }}
                                </span>
                            </span>
                            <span class="badge text-bg-{{ $providerStatus['vnpay'] ? 'light' : 'secondary' }}">
                                {{ $providerStatus['vnpay'] ? 'Sẵn sàng' : 'Chưa cấu hình' }}
                            </span>
                        </label>

                        <label class="payment-option border rounded-4 p-3 mb-4 d-flex align-items-center gap-3 {{ $providerStatus['sepay'] ? '' : 'opacity-50' }}">
                            <input class="form-check-input flex-shrink-0" type="radio" name="provider" value="sepay"
                                {{ old('provider') === 'sepay' ? 'checked' : '' }}
                                {{ $providerStatus['sepay'] ? '' : 'disabled' }}>
                            <span class="brand-mark flex-shrink-0" style="background:#1261d8"><i class="bi bi-qr-code-scan"></i></span>
                            <span class="flex-grow-1">
                                <strong class="d-block">Chuyển khoản QR SePay</strong>
                                <span class="small text-muted">Quét QR bằng ứng dụng ngân hàng để chuyển khoản đúng số tiền</span>
                            </span>
                            <span class="badge text-bg-{{ $providerStatus['sepay'] ? 'light' : 'secondary' }}">
                                {{ $providerStatus['sepay'] ? 'Sẵn sàng' : 'Chưa cấu hình' }}
                            </span>
                        </label>

                        @error('provider')
                            <div class="text-danger small mb-3">{{ $message }}</div>
                        @enderror

                        <button class="btn btn-primary btn-lg w-100" type="submit">
                            Tiếp tục thanh toán <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                        <p class="small text-muted text-center mt-3 mb-0">
                            Không đóng trình duyệt cho đến khi hệ thống hiển thị kết quả giao dịch.
                        </p>
                    </form>
                </div>

                <div class="col-lg-5">
                    <aside class="card bg-dark text-white p-4 p-md-5 shadow-soft sticky-lg-top" style="top:92px">
                        <div class="small text-white-50">ĐƠN HÀNG</div>
                        <h4 class="fw-bold mt-1">{{ $booking->showtime->movie->title }}</h4>
                        <div class="small text-white-50">{{ $booking->code }}</div>
                        <hr>
                        <div class="small text-white-50">Suất chiếu</div>
                        <div class="mb-3">{{ $booking->showtime->starts_at->format('H:i · d/m/Y') }}</div>
                        <div class="small text-white-50">Rạp / Phòng</div>
                        <div class="mb-3">{{ $booking->showtime->room->cinema->name }} / {{ $booking->showtime->room->name }}</div>
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
</script>
@endpush
