@extends('layouts.app')
@section('title','Xác nhận hủy suất')
@section('content')
<div class="container py-4">@include('admin.partials.nav')
<h1 class="h3">Kiểm tra trước khi hủy suất</h1><p>Lý do: {{ $data['reason'] }}</p>
<div class="alert alert-warning">Ngừng bán và check-in các suất đã chọn. Đơn chưa thanh toán được giải phóng ghế; đơn đã thanh toán được đưa vào đối soát. Khách nhận thông báo trong tài khoản và hệ thống thử gửi email. Không tự chuyển tiền.</div>
@foreach($showtimes as $showtime)<div class="card p-3 mb-2"><strong>{{ $showtime->movie->title }}</strong><div>{{ $showtime->room->cinema->name }} · {{ $showtime->starts_at->format('H:i d/m/Y') }}</div><div>{{ $showtime->bookings_count }} đơn, {{ $showtime->paid_count }} đơn đã thanh toán</div></div>@endforeach
<form method="POST" action="{{ route('admin.showtimes.cancel-bulk') }}">@csrf<input type="hidden" name="token" value="{{ $token }}"><button class="btn btn-danger">Xác nhận hủy các suất này</button> <a class="btn btn-light" href="{{ route('admin.showtimes.index') }}">Quay lại</a></form></div>
@endsection
