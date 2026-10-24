@extends('layouts.app')
@section('title', 'Bắp nước')
@section('content')
<div class="container py-4">
    @include('admin.partials.nav')
    <div class="d-flex justify-content-between align-items-center mb-3"><h1 class="section-title">Bắp nước</h1><a href="{{ route('admin.concessions.create') }}" class="btn btn-primary">Thêm combo</a></div>
    <div class="card p-3 table-responsive"><table class="table align-middle">
        <thead><tr><th>Combo</th><th>Giá</th><th>Trạng thái</th><th>Thứ tự</th><th>Thao tác</th></tr></thead>
        <tbody>
        @forelse($concessions as $item)
            <tr>
                <td><strong>{{ $item->name }}</strong><div class="small text-muted">{{ $item->description }}</div></td>
                <td>{{ number_format($item->price, 0, ',', '.') }}₫</td>
                <td><span class="badge text-bg-{{ $item->is_active ? 'success' : 'secondary' }}">{{ $item->is_active ? 'Đang bán' : 'Đã tắt' }}</span></td>
                <td>{{ $item->sort_order }}</td>
                <td><div class="d-flex gap-2"><a href="{{ route('admin.concessions.edit', $item) }}" class="btn btn-outline-dark btn-sm">Sửa</a><form method="POST" action="{{ route('admin.concessions.destroy', $item) }}">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm">Xóa</button></form></div></td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-muted">Chưa có combo bắp nước.</td></tr>
        @endforelse
        </tbody>
    </table>{{ $concessions->links() }}</div>
    <p class="small text-muted mt-3">Combo đã có trong đơn chỉ được tắt; giá đã đặt được giữ nguyên trong lịch sử.</p>
</div>
@endsection
