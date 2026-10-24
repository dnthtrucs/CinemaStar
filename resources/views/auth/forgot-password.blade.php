@extends('layouts.auth')

@section('title', 'Quên mật khẩu')
@section('heading', 'Quên mật khẩu')
@section('subheading', 'Nhập email đã đăng ký, chúng tôi sẽ gửi đường dẫn để bạn đặt mật khẩu mới.')

@section('form')
    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="mb-4">
            <label class="form-label" for="email">Email</label>
            <input id="email" type="email" class="form-control form-control-lg" name="email" value="{{ old('email') }}" required autofocus>
        </div>
        <button class="btn btn-primary btn-lg w-100">Gửi đường dẫn đặt lại mật khẩu</button>
    </form>
    <div class="text-center mt-4"><a href="{{ route('login') }}">Quay lại đăng nhập</a></div>
@endsection
