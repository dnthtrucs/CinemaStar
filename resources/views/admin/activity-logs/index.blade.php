@extends('layouts.app')
@section('title', 'Nhật ký hoạt động')
@section('content')
<div class="container py-4">
    @include('admin.partials.nav')
    <h1 class="h3">Nhật ký hoạt động</h1>
    <p class="text-muted">Tra cứu lịch sử thanh toán, hoàn tiền, check-in và đánh giá. Ngày giờ theo Việt Nam.</p>
    <form method="GET" action="{{ route('admin.activity-logs.index') }}" class="card p-3 my-3">
        <div class="row g-3 align-items-end">
            <div class="col-sm-6 col-lg-2">
                <label for="log-group" class="form-label">Nhóm hành động</label>
                <select id="log-group" name="group" class="form-select">
                    <option value="">Tất cả</option>
                    @foreach($groups as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['group'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-6 col-lg-3">
                <label for="log-actor" class="form-label">Tên hoặc email người thực hiện</label>
                <input id="log-actor" name="actor" class="form-control" maxlength="255" value="{{ $filters['actor'] ?? '' }}">
            </div>
            <div class="col-sm-6 col-lg-2">
                <label for="log-from" class="form-label">Từ ngày</label>
                <input id="log-from" type="date" name="from" class="form-control" value="{{ $filters['from'] ?? '' }}">
            </div>
            <div class="col-sm-6 col-lg-2">
                <label for="log-to" class="form-label">Đến ngày</label>
                <input id="log-to" type="date" name="to" class="form-control" value="{{ $filters['to'] ?? '' }}">
            </div>
            <div class="col-lg-3 d-flex gap-2">
                <button class="btn btn-primary">Lọc</button>
                <a href="{{ route('admin.activity-logs.index') }}" class="btn btn-outline-secondary">Xóa bộ lọc</a>
            </div>
        </div>
    </form>
    <div class="card p-3 table-responsive">
        <div class="small text-muted mb-3">{{ number_format($logs->total(), 0, ',', '.') }} bản ghi</div>
        <table class="table align-middle">
            <thead><tr><th>Thời gian</th><th>Người thực hiện</th><th>Hành động</th><th>Nội dung</th><th>Liên quan</th></tr></thead>
            <tbody>
            @forelse($logs as $log)
                @php($context = $related[$log->id])
                <tr>
                    <td class="text-nowrap">{{ $log->created_at->timezone('Asia/Ho_Chi_Minh')->format('H:i:s d/m/Y') }}<div class="small text-muted">#{{ $log->id }}</div></td>
                    <td>
                        @if($log->user)
                            {{ $log->user->name }}<div class="small text-muted">{{ $log->user->email }}</div>
                        @else
                            <span class="text-muted">Hệ thống / tài khoản đã xóa</span>
                        @endif
                    </td>
                    <td><span class="badge text-bg-secondary">{{ $log->action }}</span></td>
                    <td>{{ $log->description }}</td>
                    <td>
                        <div class="small text-muted">{{ $context['label'] }}</div>
                        @if($context['booking'])
                            <a href="{{ route('admin.bookings.show', $context['booking']) }}">Đơn {{ $context['booking']->code }}</a>
                        @endif
                        @if($context['payment'] && $context['booking'])
                            <div><a href="{{ route('admin.bookings.show', $context['booking']) }}#payment-{{ $context['payment']->id }}">Thanh toán {{ $context['payment']->request_id }}</a></div>
                        @endif
                        @if($context['review'])
                            <a href="{{ route('admin.reviews.index') }}">Danh sách đánh giá</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-muted">Không có nhật ký phù hợp.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $logs->links() }}
    </div>
</div>
@endsection
