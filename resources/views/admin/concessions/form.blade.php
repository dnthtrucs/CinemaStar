@extends('layouts.app')
@section('title', $concession->exists ? 'Sửa combo' : 'Thêm combo')
@section('content')
<div class="container py-4">
    @include('admin.partials.nav')
    <h1 class="h3 mb-4">{{ $concession->exists ? 'Sửa combo bắp nước' : 'Thêm combo bắp nước' }}</h1>
    <form class="card p-4 shadow-soft" method="POST" enctype="multipart/form-data" action="{{ $concession->exists ? route('admin.concessions.update', $concession) : route('admin.concessions.store') }}">
        @csrf @if($concession->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-md-8"><label for="name" class="form-label">Tên combo</label><input id="name" name="name" class="form-control" value="{{ old('name', $concession->name) }}" required maxlength="255"></div>
            <div class="col-md-4"><label for="price" class="form-label">Giá (₫)</label><input id="price" name="price" type="number" min="0" max="4294967295" step="1" class="form-control" value="{{ old('price', $concession->price) }}" required></div>
            <div class="col-12"><label for="description" class="form-label">Mô tả</label><textarea id="description" name="description" class="form-control" maxlength="2000">{{ old('description', $concession->description) }}</textarea></div>
            <div class="col-md-8"><label for="image" class="form-label">Ảnh (JPG, PNG, WebP; tối đa 5MB)</label><input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" class="form-control">@if($concession->image_path)<img src="{{ asset('storage/'.$concession->image_path) }}" alt="{{ $concession->name }}" class="rounded mt-2" width="120">@endif</div>
            <div class="col-md-4"><label for="sort_order" class="form-label">Thứ tự hiển thị</label><input id="sort_order" name="sort_order" type="number" min="0" max="9999" class="form-control" value="{{ old('sort_order', $concession->sort_order ?? 0) }}"></div>
            <div class="col-12"><input type="hidden" name="is_active" value="0"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $concession->is_active))> Đang bán</label></div>
        </div>
        <div class="d-flex gap-2 mt-4"><button class="btn btn-primary">Lưu combo</button><a href="{{ route('admin.concessions.index') }}" class="btn btn-outline-dark">Quay lại</a></div>
    </form>
</div>
@endsection
