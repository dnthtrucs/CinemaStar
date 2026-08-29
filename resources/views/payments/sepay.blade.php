@extends('layouts.app')

@section('title', 'Thanh toán SePay')

@section('content')
<div class="container py-5"><div class="row justify-content-center"><div class="col-lg-7"><div class="card shadow-soft p-4 p-md-5 text-center">
    <div class="display-5 text-primary"><i class="bi bi-qr-code-scan"></i></div>
    <h1 class="h2 fw-bold mt-2">Quét mã QR để thanh toán</h1>
    <p class="text-muted">Dùng ứng dụng ngân hàng để chuyển khoản đúng số tiền. Vé được kích hoạt sau khi SePay xác nhận.</p>
    <img src="{{ $paymentQrUrl }}" width="320" height="320" class="img-fluid border rounded-4 p-2 my-2" alt="Mã QR thanh toán SePay">
    <div class="bg-light rounded-4 p-3 mt-3 text-start"><div class="d-flex justify-content-between"><span>Số tiền</span><strong class="fs-4 text-brand">{{ number_format($payment->amount, 0, ',', '.') }}₫</strong></div><div class="d-flex justify-content-between small mt-2"><span>Nội dung chuyển khoản</span><strong>{{ $payment->request_id }}</strong></div></div>
    <div id="paymentWaiting" class="small text-muted mt-4"><span class="spinner-border spinner-border-sm me-2"></span>Đang chờ SePay xác nhận giao dịch…</div>
    <a class="btn btn-outline-secondary mt-3" href="{{ route('bookings.show', $payment->booking_id) }}">Quay lại đơn hàng</a>
</div></div></div></div>
@endsection

@push('scripts')
<script>
const statusUrl = @json(route('payments.sepay.status', $payment));
const timer = setInterval(async () => { try { const result = await (await fetch(statusUrl, {headers:{Accept:'application/json'}})).json(); if (result.status === 'success') { clearInterval(timer); location.assign(result.redirect_url); } } catch (_) {} }, 4000);
</script>
@endpush
