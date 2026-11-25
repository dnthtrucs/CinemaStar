@extends('layouts.app')
@section('title','Không thể sử dụng mã vé hoặc yêu cầu này')
@section('content')
<div class="container py-5" style="max-width:700px"><section class="card p-4 shadow-soft"><h1 class="h4">Không thể thực hiện yêu cầu</h1><p class="text-danger" role="alert">{{ $exception->getMessage() ?: 'Mã vé không hợp lệ hoặc yêu cầu không còn hiệu lực. Vui lòng kiểm tra lại.' }}</p>
@auth @if(auth()->user()->isStaff())<a class="btn btn-primary" href="{{ auth()->user()->isAdmin() ? route('admin.tickets.index') : route('staff.tickets.index') }}">Quay lại quét mã vé mới</a>@else<a class="btn btn-primary" href="{{ route('bookings.index') }}">Mở Vé của tôi</a>@endif @else<a class="btn btn-primary" href="{{ route('home') }}">Về trang chủ</a>@endauth
</section></div>
@endsection
