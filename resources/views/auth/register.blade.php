@extends('layouts.auth')

@section('title', 'Đăng ký')
@section('auth_width', 'col-md-7 col-lg-6')
@section('heading', 'Tạo tài khoản CinemaStar')
@section('subheading', 'Lưu vé và quản lý lịch sử đặt chỗ')

@section('form')
    <form method="POST" action="{{ route('register') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">Họ và tên *</label>
            <input class="form-control" name="name" value="{{ old('name') }}" required autofocus>
        </div>
        <div class="row g-3">
            <div class="col-md-7">
                <label class="form-label">Email *</label>
                <input type="email" class="form-control" name="email" value="{{ old('email') }}" required>
            </div>
            <div class="col-md-5">
                <label class="form-label">Số điện thoại</label>
                <input class="form-control" name="phone" value="{{ old('phone') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Mật khẩu *</label>
                <input type="password" class="form-control" name="password" required autocomplete="new-password">
            </div>
            <div class="col-md-6">
                <label class="form-label">Xác nhận mật khẩu *</label>
                <input type="password" class="form-control" name="password_confirmation" required>
            </div>
        </div>
        <button class="btn btn-primary btn-lg w-100 mt-4">Đăng ký</button>
    </form>
    <div class="text-center mt-4">Đã có tài khoản? <a href="{{ route('login') }}">Đăng nhập</a></div>
@endsection
