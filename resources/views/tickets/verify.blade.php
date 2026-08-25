@extends('layouts.app')

@section('title', 'Xác thực vé '.$ticket->code)

@php
    $isPaid = $ticket->booking->payment_status === 'paid';
    $isConfirmed = $ticket->booking->status === 'confirmed';
    $isValid = $isPaid && $isConfirmed && $ticket->status === 'valid';
    $isUsed = $ticket->status === 'used';

    [$statusLabel, $statusClass, $statusIcon] = match (true) {
        $isUsed => ['Vé đã được sử dụng', 'warning', 'bi-exclamation-circle-fill'],
        $isValid => ['Vé hợp lệ', 'success', 'bi-patch-check-fill'],
        default => ['Vé chưa có hiệu lực', 'secondary', 'bi-hourglass-split'],
    };
@endphp

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="text-center mb-4">
                <div class="brand-mark mx-auto mb-3"><i class="bi bi-qr-code-scan"></i></div>
                <div class="text-brand fw-semibold">CINEMASTAR</div>
                <h1 class="section-title">Xác thực vé điện tử</h1>
            </div>

            <div class="card shadow-soft overflow-hidden">
                <div class="bg-{{ $statusClass }} text-white p-4 text-center">
                    <div class="display-5"><i class="bi {{ $statusIcon }}"></i></div>
                    <h3 class="fw-bold mb-1">{{ $statusLabel }}</h3>
                    <div class="opacity-75">Mã vé {{ $ticket->code }}</div>
                </div>

                <div class="p-4 p-md-5">
                    <div class="row g-4">
                        <div class="col-sm-8">
                            <div class="small text-muted">Phim</div>
                            <h4 class="fw-bold">{{ $ticket->showtime->movie->title }}</h4>
                        </div>
                        <div class="col-sm-4">
                            <div class="small text-muted">Ghế</div>
                            <div class="fs-4 fw-bold text-brand">{{ $ticket->seat->label }}</div>
                        </div>
                        <div class="col-sm-6">
                            <div class="small text-muted">Thời gian</div>
                            <strong>{{ $ticket->showtime->starts_at->format('H:i · d/m/Y') }}</strong>
                        </div>
                        <div class="col-sm-6">
                            <div class="small text-muted">Rạp / Phòng</div>
                            <strong>{{ $ticket->showtime->room->cinema->name }} / {{ $ticket->showtime->room->name }}</strong>
                        </div>
                    </div>

                    @if($isUsed)
                        <div class="alert alert-warning mt-4 mb-0">
                            Vé đã check-in lúc <strong>{{ $ticket->checked_in_at?->format('H:i · d/m/Y') }}</strong>.
                        </div>
                    @elseif(!$isValid)
                        <div class="alert alert-secondary mt-4 mb-0">
                            Vé chỉ có hiệu lực sau khi đơn đặt vé được thanh toán thành công.
                        </div>
                    @endif

                    <div class="d-grid mt-4">
                        <a class="btn btn-dark btn-lg" href="{{ route('admin.tickets.index', ['code' => $ticket->code]) }}">
                            <i class="bi bi-person-badge me-2"></i>Nhân viên kiểm tra / check-in
                        </a>
                    </div>
                </div>
            </div>

            <p class="text-muted small text-center mt-3 mb-0">
                Trang xác thực không hiển thị tên, email hoặc số điện thoại của khách hàng.
            </p>
        </div>
    </div>
</div>
@endsection
