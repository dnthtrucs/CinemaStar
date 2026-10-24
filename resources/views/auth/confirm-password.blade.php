@extends('layouts.auth')

@section('title', 'Xác nhận mật khẩu')
@section('heading', 'Xác nhận mật khẩu')
@section('subheading', 'Đây là khu vực bảo mật. Vui lòng nhập lại mật khẩu để tiếp tục.')

@section('form')
    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf
        <div class="mb-4">
            <label class="form-label" for="password">Mật khẩu</label>
            <input id="password" type="password" class="form-control form-control-lg" name="password" required autocomplete="current-password">
        </div>
        <button class="btn btn-primary btn-lg w-100">Xác nhận</button>
    </form>
@endsection
