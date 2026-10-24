@extends('layouts.auth')

@section('title', 'Xác thực email')
@section('heading', 'Xác thực email')
@section('subheading', 'Cảm ơn bạn đã đăng ký! Vui lòng bấm vào đường dẫn trong email chúng tôi vừa gửi để xác thực địa chỉ email.')

@section('form')
    @if(session('status') === 'verification-link-sent')
        <div class="alert alert-success">Đường dẫn xác thực mới đã được gửi tới email bạn dùng khi đăng ký.</div>
    @endif
    <div class="d-flex justify-content-between align-items-center gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button class="btn btn-primary">Gửi lại email xác thực</button>
        </form>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-link">Đăng xuất</button>
        </form>
    </div>
@endsection
