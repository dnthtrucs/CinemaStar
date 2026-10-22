@extends('layouts.auth')

@section('title', 'Đặt lại mật khẩu')
@section('heading', 'Đặt lại mật khẩu')

@section('form')
    <form method="POST" action="{{ route('password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <input id="email" type="email" class="form-control" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username">
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">Mật khẩu mới</label>
            <input id="password" type="password" class="form-control" name="password" required autocomplete="new-password">
        </div>
        <div class="mb-4">
            <label class="form-label" for="password_confirmation">Xác nhận mật khẩu mới</label>
            <input id="password_confirmation" type="password" class="form-control" name="password_confirmation" required autocomplete="new-password">
        </div>
        <button class="btn btn-primary btn-lg w-100">Đặt lại mật khẩu</button>
    </form>
@endsection
