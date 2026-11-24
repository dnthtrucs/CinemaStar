@extends('layouts.app')
@section('title', 'Thông báo')
@section('content')
<div class="container py-4" style="max-width:850px">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><h1 class="h3 fw-bold mb-0">Thông báo của bạn</h1><form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn btn-outline-secondary btn-sm">Đánh dấu tất cả đã đọc</button></form></div>
<p class="text-muted">Vé đã xác nhận luôn có trong Vé của tôi, kể cả khi email đến chậm.</p>
@forelse($notifications as $notification)
<article class="card p-3 mb-3 {{ $notification->read_at ? '' : 'border-primary' }}">
<h2 class="h6 fw-bold">{{ $notification->data['title'] ?? 'Thông báo CinemaStar' }}</h2>
<p>{{ $notification->data['message'] ?? $notification->data['status'] ?? 'Bạn có cập nhật mới trong tài khoản.' }}</p>
<small class="text-muted">{{ $notification->created_at->format('H:i d/m/Y') }}</small>
<div class="d-flex gap-2 mt-2">
@if(isset($notification->data['booking_id']))<a class="btn btn-primary btn-sm" href="{{ route('bookings.show', $notification->data['booking_id']) }}">Mở vé / xem đơn</a>@endif
@if(!$notification->read_at)<form method="POST" action="{{ route('notifications.read', $notification->id) }}">@csrf<button class="btn btn-outline-secondary btn-sm">Đánh dấu đã đọc</button></form>@endif
</div></article>
@empty<div class="alert alert-light">Chưa có thông báo mới.</div>@endforelse
{{ $notifications->links() }}
</div>
@endsection
