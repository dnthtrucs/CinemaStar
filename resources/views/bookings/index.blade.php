@extends('layouts.app')
@section('title', 'Vé của tôi')
@section('content')
<div class="container py-4">
    <h1 class="section-title">Vé của tôi</h1>
    @forelse($bookings as $booking)
        <div class="card shadow-soft p-3 p-md-4 mb-3"><div class="row align-items-center g-3">
            <div class="col-md-2"><div class="small text-muted">Mã đơn</div><div class="fw-bold">{{ $booking->code }}</div>
                <span class="badge text-bg-{{ $booking->ticket_status_badge }} mt-2">{{ $booking->ticket_status_label }}</span></div>
            <div class="col-md-4"><a href="{{ route('bookings.show', $booking) }}" class="fw-bold fs-5 text-dark text-decoration-none">{{ $booking->showtime->movie->title }}</a><div class="small text-muted">{{ $booking->showtime->room->cinema->name }} · {{ $booking->showtime->room->name }}</div></div>
            <div class="col-md-3"><div class="fw-semibold">{{ $booking->showtime->starts_at->format('H:i · d/m/Y') }}</div><div class="small text-muted">Ghế {{ $booking->tickets->pluck('seat.label')->join(', ') }}</div></div>
            <div class="col-md-2"><div class="small text-muted">Tổng tiền</div><div class="fw-bold text-brand">{{ number_format($booking->total_price, 0, ',', '.') }}₫</div></div>
            <div class="col-md-1 text-md-end"><a href="{{ route('bookings.show', $booking) }}" class="btn btn-outline-dark btn-sm"><i class="bi bi-chevron-right"></i></a></div>
        </div></div>
    @empty
        <div class="card p-5 text-center shadow-soft"><h4 class="fw-bold">Bạn chưa có vé nào</h4><a href="{{ route('movies.index') }}" class="btn btn-primary">Khám phá phim</a></div>
    @endforelse
    <div class="mt-4">{{ $bookings->links() }}</div>
</div>
@endsection
