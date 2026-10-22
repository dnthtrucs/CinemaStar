@extends('layouts.auth')

@section('title', 'Đăng nhập')
@section('heading', 'Chào mừng trở lại')
@section('subheading', 'Đăng nhập để tiếp tục đặt vé')

@section('form')
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control form-control-lg" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        </div>
        <div class="mb-3">
            <label class="form-label">Mật khẩu</label>
            <input type="password" class="form-control form-control-lg" name="password" required autocomplete="current-password">
        </div>
        <div class="d-flex justify-content-between mb-4">
            <label><input type="checkbox" name="remember"> Ghi nhớ đăng nhập</label>
            @if(Route::has('password.request'))
                <a href="{{ route('password.request') }}">Quên mật khẩu?</a>
            @endif
        </div>
        <button class="btn btn-primary btn-lg w-100">Đăng nhập</button>
    </form>
    <div class="text-center mt-4">Chưa có tài khoản? <a href="{{ route('register') }}">Đăng ký ngay</a></div>
@endsection
