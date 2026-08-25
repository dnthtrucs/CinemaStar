@extends('layouts.app')
@section('title','Quản lý banner')
@section('content')
<div class="container py-4">
    @include('admin.partials.nav')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><div class="text-brand fw-semibold">TRANG CHỦ</div><h1 class="section-title mb-0">Banner slider</h1></div>
        <a href="{{ route('admin.banners.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Thêm banner</a>
    </div>
    <div class="alert alert-light border small">Nên dùng ảnh ngang tỷ lệ 16:6 hoặc 16:7, kích thước từ 1600 × 600 px để banner hiển thị đẹp trên máy tính và điện thoại.</div>
    <div class="card shadow-soft p-3">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Ảnh</th><th>Nội dung</th><th>Thứ tự</th><th>Trạng thái</th><th></th></tr></thead>
                <tbody>
                @forelse($banners as $banner)
                    <tr>
                        <td><img src="{{ asset('storage/'.$banner->image_path) }}" alt="{{ $banner->title }}" class="rounded" style="width:140px;height:55px;object-fit:cover"></td>
                        <td><strong>{{ $banner->title }}</strong><div class="small text-muted">{{ $banner->subtitle }}</div></td>
                        <td>{{ $banner->sort_order }}</td>
                        <td><span class="badge text-bg-{{ $banner->is_active ? 'success' : 'secondary' }}">{{ $banner->is_active ? 'Đang hiển thị' : 'Đang ẩn' }}</span></td>
                        <td class="text-end"><a class="btn btn-outline-dark btn-sm" href="{{ route('admin.banners.edit',$banner) }}"><i class="bi bi-pencil"></i></a><form class="d-inline" method="POST" action="{{ route('admin.banners.destroy',$banner) }}" onsubmit="return confirm('Xóa banner này?')">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button></form></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-5">Chưa có banner. Hãy thêm banner đầu tiên cho trang chủ.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $banners->links() }}
    </div>
</div>
@endsection
