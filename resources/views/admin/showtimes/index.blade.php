@extends('layouts.app')
@section('title','Quản lý suất chiếu')
@section('content')
    <div class="container py-4">
        @include('admin.partials.nav')
        <div class="d-flex flex-wrap gap-2 justify-content-between mb-3">
            <h1 class="section-title mb-0">Quản lý suất chiếu</h1>
            <div>
                <a href="{{ route('admin.showtimes.bulk.create') }}" class="btn btn-outline-primary">Tạo hàng loạt</a>
                <a href="{{ route('admin.showtimes.create') }}" class="btn btn-primary">Thêm suất chiếu</a>
            </div>
        </div>
        <form id="bulkCancelForm" method="POST" action="{{ route('admin.showtimes.cancel-preview') }}" class="card p-3 mb-3">@csrf<label for="cancelReason" class="form-label">Hủy nhiều suất: chọn các ô bên dưới và nhập lý do</label><div class="d-flex gap-2"><input id="cancelReason" class="form-control" name="reason" minlength="5" maxlength="500" required placeholder="Ví dụ: phòng chiếu cần bảo trì"><button class="btn btn-outline-danger text-nowrap">Xem trước hủy suất</button></div></form>
        <div class="card shadow-soft p-3">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Chọn hủy</th><th>Thời gian</th>
                            <th>Phim</th>
                            <th>Rạp / Phòng</th>
                            <th>Giá</th>
                            <th>Trạng thái</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($showtimes as $showtime)
                            <tr>
                                <td>@if($showtime->status === 'scheduled' && $showtime->starts_at->isFuture())<input type="checkbox" name="showtime_ids[]" value="{{ $showtime->id }}" form="bulkCancelForm" aria-label="Chọn hủy suất {{ $showtime->id }}">@endif</td>
                                <td>
                                    <strong>{{ $showtime->starts_at->format('H:i') }}</strong>
                                    <div class="small text-muted">{{ $showtime->starts_at->format('d/m/Y') }}</div>
                                </td>
                                <td class="fw-semibold">{{ $showtime->movie->title }}</td>
                                <td>
                                    {{ $showtime->room->cinema->name }}
                                    <div class="small text-muted">{{ $showtime->room->name }} · {{ $showtime->format }}</div>
                                </td>
                                <td>{{ number_format($showtime->base_price,0,',','.') }}₫</td>
                                <td>
                                    <span class="badge text-bg-{{ $showtime->status==='scheduled'?'success':'danger' }}">{{ $showtime->status === 'scheduled' ? 'Đang hoạt động' : 'Đã hủy' }}</span>
                                </td>
                                <td class="text-end">
                                    <a class="btn btn-outline-dark btn-sm" href="{{ route('admin.showtimes.edit',$showtime) }}">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form class="d-inline" method="POST" action="{{ route('admin.showtimes.destroy',$showtime) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-outline-danger btn-sm">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Chưa có suất chiếu.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $showtimes->links() }}
        </div>
    </div>
@endsection
