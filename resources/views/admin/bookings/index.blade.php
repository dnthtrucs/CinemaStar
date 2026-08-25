@extends('layouts.app')
@section('title', 'Quản lý đơn vé')
@section('content')
<div class="container py-4">@include('admin.partials.nav')
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-3"><h1 class="section-title">Quản lý đơn vé</h1><form class="d-flex gap-2"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Mã đơn"><select class="form-select" name="status"><option value="">Tất cả</option>@foreach(['pending' => 'Chờ thanh toán', 'confirmed' => 'Đã thanh toán', 'cancelled' => 'Đã hủy', 'expired' => 'Đã hết hạn'] as $status => $label)<option @selected(request('status') === $status)>{{ $label }}</option>@endforeach</select><button class="btn btn-dark">Lọc</button></form></div>
    <div class="card shadow-soft p-3"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Mã đơn</th><th>Khách hàng</th><th>Phim / Suất</th><th>Ghế</th><th>Trạng thái vé</th><th>Tổng tiền</th><th></th></tr></thead><tbody>
    @foreach($bookings as $booking)<tr><td class="fw-bold">{{ $booking->code }}</td><td>{{ $booking->user->name }}<div class="small text-muted">{{ $booking->user->email }}</div></td><td>{{ $booking->showtime->movie->title }}<div class="small text-muted">{{ $booking->showtime->starts_at->format('H:i d/m/Y') }}</div></td><td>{{ $booking->tickets->pluck('seat.label')->join(', ') }}</td><td><span class="badge text-bg-{{ $booking->ticket_status_badge }}">{{ $booking->ticket_status_label }}</span></td><td class="fw-bold">{{ number_format($booking->total_price, 0, ',', '.') }}₫</td><td><a href="{{ route('admin.bookings.show', $booking) }}"><i class="bi bi-chevron-right"></i></a></td></tr>@endforeach
    </tbody></table></div>{{ $bookings->links() }}</div>
</div>
@endsection
