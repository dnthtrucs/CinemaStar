@extends('layouts.app')
@section('title',$banner->exists ? 'Sửa banner' : 'Thêm banner')
@section('content')
<div class="container py-4">
    @include('admin.partials.nav')
    <div class="row justify-content-center"><div class="col-lg-8">
        <h1 class="section-title mb-1">{{ $banner->exists ? 'Cập nhật banner' : 'Thêm banner trang chủ' }}</h1>
        <p class="text-muted">Banner sẽ xuất hiện trong slider ở đầu trang chủ khi ở trạng thái hiển thị.</p>
        <form class="card shadow-soft p-4 mt-3" method="POST" enctype="multipart/form-data" action="{{ $banner->exists ? route('admin.banners.update',$banner) : route('admin.banners.store') }}">
            @csrf @if($banner->exists) @method('PUT') @endif
            <div class="row g-3">
                <div class="col-12"><label class="form-label">Tiêu đề *</label><input class="form-control @error('title') is-invalid @enderror" name="title" value="{{ old('title',$banner->title) }}" required>@error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12"><label class="form-label">Mô tả ngắn</label><input class="form-control @error('subtitle') is-invalid @enderror" name="subtitle" value="{{ old('subtitle',$banner->subtitle) }}">@error('subtitle')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12"><label class="form-label">Ảnh banner {{ $banner->exists ? '' : '*' }}</label><input type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="form-control @error('image') is-invalid @enderror" name="image" {{ $banner->exists ? '' : 'required' }}>@error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror<div class="form-text">JPG, PNG hoặc WebP; tối đa 5MB; khuyến nghị 1600 × 600 px.</div>@if($banner->image_path)<img src="{{ asset('storage/'.$banner->image_path) }}" alt="{{ $banner->title }}" class="img-fluid rounded mt-3 border" style="max-height:220px">@endif</div>
                <div class="col-md-6"><label class="form-label">Nhãn nút</label><input class="form-control" name="button_label" value="{{ old('button_label',$banner->button_label ?: 'Đặt vé ngay') }}" placeholder="Ví dụ: Đặt vé ngay"></div>
                <div class="col-md-6"><label class="form-label">Liên kết nút</label><input type="url" class="form-control @error('button_url') is-invalid @enderror" name="button_url" value="{{ old('button_url',$banner->button_url) }}" placeholder="https://...">@error('button_url')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label">Thứ tự hiển thị</label><input type="number" min="0" class="form-control" name="sort_order" value="{{ old('sort_order',$banner->sort_order ?: 0) }}"></div>
                <div class="col-md-6 d-flex align-items-end"><label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active',$banner->exists ? $banner->is_active : true))> <span class="form-check-label">Hiển thị banner trên trang chủ</span></label></div>
            </div>
            <div class="mt-4"><button class="btn btn-primary">Lưu banner</button><a class="btn btn-light" href="{{ route('admin.banners.index') }}">Hủy</a></div>
        </form>
    </div></div>
</div>
@endsection
