@extends('layouts.app')
@section('title', 'Thanh toán ')
@section('content')
<div class="container py-5"><div class="row justify-content-center"><div class="col-md-6"><div class="card shadow-soft p-5 text-center"><div class="display-3 text-brand"><i class="bi bi-credit-card-2-front"></i></div><h2 class="fw-bold mt-3">Cổng thanh toán </h2><p class="text-muted">Chỉ dùng cho demo , không phát sinh giao dịch thật.</p><div class="bg-light rounded p-3 my-3"><div class="small text-muted">Số tiền</div><div class="display-6 fw-bold">{{ number_format($payment->amount,0,',','.') }}₫</div><div class="small">Mã yêu cầu: {{ $payment->request_id }}</div></div><form method="POST" action="{{ route('payments.demo.complete',$payment) }}">@csrf<button class="btn btn-primary btn-lg w-100">Xác nhận thanh toán thành công</button></form><a href="{{ route('bookings.show',$payment->booking_id) }}" class="mt-3">Quay lại đơn hàng</a></div></div></div></div>
@endsection
