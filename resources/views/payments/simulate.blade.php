@extends('layouts.app')

@php
    $isMomo = $payment->provider === 'momo';
    $providerName = $isMomo ? 'MoMo' : 'VNPAY';
    $providerColor = $isMomo ? '#a50064' : '#075eaa';
@endphp

@section('title', ' '.$providerName)

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card shadow-soft overflow-hidden">
                <div class="p-4 p-md-5 text-white text-center" style="background:{{ $providerColor }}">
                    <div class="display-3 fw-bold">{{ $isMomo ? 'M' : 'V' }}</div>
                    <h1 class="h3 fw-bold mt-2 mb-1">Thanh toán {{ $providerName }}</h1>
                    <div class="badge text-bg-warning mt-2"> </div>
                </div>

                <div class="p-4 p-md-5">
                    <div class="alert alert-warning small">
                        Trang này  phản hồi của {{ $providerName }} trên máy cá nhân.
                        Không quét mã bằng ứng dụng thật và không có tiền được chuyển.
                    </div>

                    <div class="text-center py-3">
                        <img src="{{ $paymentQr }}" class="payment-qr border rounded-4 p-2 bg-white" alt="Mã QR thanh toán {{ $providerName }}">
                        <p class="small text-muted mt-3 mb-0">Dùng điện thoại quét QR để mở trang xác nhận thanh toán {{ $providerName }} mô phỏng.</p>
                    </div>

                    <div class="bg-light rounded-4 p-4 my-4">
                        <div class="d-flex justify-content-between gap-3 mb-2">
                            <span class="text-muted">Đơn hàng</span>
                            <strong>{{ $payment->booking->code }}</strong>
                        </div>
                        <div class="d-flex justify-content-between gap-3 mb-2">
                            <span class="text-muted">Nội dung</span>
                            <strong class="text-end">{{ $payment->booking->showtime->movie->title }}</strong>
                        </div>
                        <div class="d-flex justify-content-between gap-3 border-top pt-3 mt-3">
                            <span class="text-muted">Số tiền</span>
                            <strong class="fs-4" style="color:{{ $providerColor }}">{{ number_format($payment->amount, 0, ',', '.') }}₫</strong>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('payments.simulate.complete', $payment) }}" class="d-grid gap-2">
                        @csrf
                        <button class="btn btn-lg text-white" style="background:{{ $providerColor }}" name="result" value="success">
                            <i class="bi bi-check-circle me-1"></i>Xác nhận thanh toán thành công
                        </button>
                        <button class="btn btn-outline-secondary" name="result" value="cancelled">
                            Hủy giao dịch
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>.payment-qr { width:210px; height:210px; }</style>
@endpush
