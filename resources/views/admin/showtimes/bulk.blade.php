@extends('layouts.app')
@section('title','Tạo suất chiếu hàng loạt')
@section('content')
<div class="container py-4">
    @include('admin.partials.nav')
    <div class="row justify-content-center"><div class="col-lg-8">
        <h1 class="section-title">Tạo suất chiếu hàng loạt</h1>
        <p class="text-muted">Hệ thống tự tính giờ kết thúc theo thời lượng phim và cộng 15 phút chuẩn bị phòng. Suất bị trùng phòng sẽ tự bỏ qua.</p>
        <form class="card shadow-soft p-4 mt-3" method="POST" action="{{ route('admin.showtimes.bulk.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Phim *</label><select class="form-select" name="movie_id" required>@foreach($movies as $movie)<option value="{{ $movie->id }}" @selected(old('movie_id') == $movie->id)>{{ $movie->title }} ({{ $movie->duration }}')</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label">Phòng *</label><select class="form-select" name="room_id" required>@foreach($rooms as $room)<option value="{{ $room->id }}" @selected(old('room_id') == $room->id)>{{ $room->cinema->name }} · {{ $room->name }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label">Từ ngày *</label><input type="date" class="form-control" name="start_date" min="{{ now()->format('Y-m-d') }}" value="{{ old('start_date', now()->format('Y-m-d')) }}" required></div>
                <div class="col-md-6"><label class="form-label">Đến ngày *</label><input type="date" class="form-control" name="end_date" min="{{ now()->format('Y-m-d') }}" value="{{ old('end_date', now()->addDays(6)->format('Y-m-d')) }}" required></div>
                <div class="col-12"><label class="form-label">Các giờ chiếu *</label><input class="form-control" name="times" value="{{ old('times','09:00, 12:00, 15:00, 18:00, 21:00') }}" placeholder="Ví dụ: 09:00, 12:30, 15:00, 19:45" required><div class="form-text">Nhập nhiều giờ, cách nhau bằng dấu phẩy.</div></div>
                <div class="col-md-6"><label class="form-label">Giá cơ bản *</label><input type="number" step="1000" class="form-control" name="base_price" value="{{ old('base_price',80000) }}" required></div>
                <div class="col-md-2"><label class="form-label">Định dạng</label><select class="form-select" name="format">@foreach(['2D','3D','IMAX'] as $format)<option @selected(old('format','2D') === $format)>{{ $format }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label">Ngôn ngữ</label><input class="form-control" name="language" value="{{ old('language','Tiếng Việt') }}"></div>
                <div class="col-md-2"><label class="form-label">Phụ đề</label><input class="form-control" name="subtitle" value="{{ old('subtitle','Tiếng Việt') }}"></div>
                <div class="col-md-3"><label class="form-label">Trạng thái</label><select class="form-select" name="status"><option value="scheduled" @selected(old('status','scheduled') === 'scheduled')>Hoạt động</option><option value="cancelled" @selected(old('status') === 'cancelled')>Đã hủy</option></select></div>
            </div>
            <div class="mt-4"><button class="btn btn-primary">Tạo các suất chiếu</button><a class="btn btn-light" href="{{ route('admin.showtimes.index') }}">Hủy</a></div>
        </form>
    </div></div>
</div>
@endsection
