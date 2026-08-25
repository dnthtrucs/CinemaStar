@extends('layouts.app')
@section('title', 'Check-in vé')
@section('content')
<div class="container py-4">
    @if(auth()->user()->isAdmin()) @include('admin.partials.nav') @endif
    <div class="row justify-content-center"><div class="col-lg-7"><h1 class="section-title">Check-in vé</h1><p class="text-muted">Nhập <strong>mã đơn BK...</strong> trên email của khách.</p>
        <form class="card shadow-soft p-4 mb-4" method="GET"><div class="input-group input-group-lg"><input class="form-control text-uppercase" name="code" value="{{ request('code') }}" placeholder="Ví dụ: BK26081142KQQVFO" required><button class="btn btn-dark">Kiểm tra</button></div></form>
        @if(request('code'))
            @if($booking)
                <div class="card shadow-soft p-4"><div class="d-flex justify-content-between align-items-start"><div><div class="small text-muted">MÃ ĐƠN / MÃ CHECK-IN</div><h3 class="fw-bold">{{ $booking->code }}</h3></div><span class="badge h-100 text-bg-{{ $booking->ticket_status_badge }}">{{ $booking->ticket_status_label }}</span></div><hr>
                <p><strong>Khách:</strong> {{ $booking->user->name }}</p><p><strong>Phim:</strong> {{ $booking->showtime->movie->title }}</p><p><strong>Suất:</strong> {{ $booking->showtime->starts_at->format('H:i d/m/Y') }}</p><p><strong>Ghế:</strong> {{ $booking->tickets->pluck('seat.label')->filter()->join(', ') }}</p>
                @if($booking->ticket_status === 'valid')<form method="POST" action="{{ auth()->user()->isAdmin() ? route('admin.tickets.update', $booking) : route('staff.tickets.update', $booking) }}">@csrf @method('PATCH')<button class="btn btn-primary btn-lg w-100">Xác nhận check-in toàn bộ ghế</button></form>
                @elseif($booking->ticket_status === 'checked_in')<div class="alert alert-success mb-0">Đơn đã check-in lúc {{ $booking->tickets->first()?->checked_in_at?->format('H:i · d/m/Y') }}.</div>
                @elseif($booking->ticket_status === 'expired')<div class="alert alert-secondary mb-0">Suất chiếu đã kết thúc. Vé chưa check-in đã hết hiệu lực.</div>
                @else<div class="alert alert-warning mb-0">Đơn này chưa đủ điều kiện để check-in.</div>@endif</div>
            @else <div class="alert alert-danger">Không tìm thấy mã đơn.</div>
            @endif
        @endif
    </div></div>
</div>
@endsection
