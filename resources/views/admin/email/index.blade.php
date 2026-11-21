@extends('layouts.app')
@section('title','Kết nối email')
@section('content')
<div class="container py-4">
    @include('admin.partials.nav')
    <h1 class="section-title">Email gửi vé</h1>
    <div class="card shadow-soft p-4 mt-4">
        <h5 class="fw-bold">Gmail</h5>
        <p>Gửi vé điện tử và mã QR từ tài khoản Gmail của rạp.</p>
        @if($connection)
            <p class="text-success">Đã kết nối: <strong>{{ $connection->email }}</strong></p>
            @if(config('mail.default') !== 'gmail_api')
                <div class="alert alert-warning">Đã cấp quyền Gmail. Cần bật phương thức Gmail API trong cấu hình triển khai để gửi thư thật.</div>
            @else
                <div class="alert alert-success">Gmail API đã bật. Bạn có thể gửi lại email từ chi tiết đơn đã thanh toán.</div>
            @endif
        @else
            <div class="alert alert-warning">Chưa kết nối tài khoản Gmail gửi vé.</div>
        @endif
        @if(!$configured)
            <p>Thiết lập Google Client ID và Client Secret trong Render trước khi kết nối. Không nhập mật khẩu Gmail vào website.</p>
        @endif
        <form method="POST" action="{{ route('admin.email.gmail.connect') }}">
            @csrf
            <button type="submit" class="btn btn-primary" @disabled(!$configured)>{{ $connection ? 'Kết nối lại Gmail' : 'Kết nối Gmail' }}</button>
        </form>
        <p class="small text-muted mt-3 mb-0">CinemaStar xin quyền gửi thư và xác minh địa chỉ tài khoản. Ứng dụng không xin quyền đọc hay xóa thư trong hộp thư của bạn.</p>
    </div>
    <details class="mt-4">
        <summary>Thông tin thiết lập Google Cloud</summary>
        <p class="mt-3">Loại ứng dụng: Web application. Authorized redirect URI:</p>
        <code class="d-block text-break">{{ $redirectUri }}</code>
    </details>
</div>
@endsection
