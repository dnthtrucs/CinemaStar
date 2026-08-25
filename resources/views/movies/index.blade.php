@extends('layouts.app')
@section('title', 'Phim')
@section('content')
<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
        <div><div class="text-brand fw-semibold">CinemaStar</div><h1 class="section-title mb-0">Danh sách phim</h1></div>
        <form class="d-flex gap-2" method="GET">
            <input class="form-control" name="q" value="{{ request('q') }}" placeholder="Tìm tên phim...">
            <select class="form-select" name="status"><option value="">Tất cả</option><option value="now_showing" @selected(request('status')==='now_showing')>Đang chiếu</option><option value="upcoming" @selected(request('status')==='upcoming')>Sắp chiếu</option></select>
            <button class="btn btn-dark"><i class="bi bi-search"></i></button>
        </form>
    </div>
    <div class="row g-4">@forelse($movies as $movie)<div class="col-6 col-md-4 col-lg-3"><div class="card movie-card p-2 shadow-soft"><a href="{{ route('movies.show',$movie) }}"><img class="movie-poster" src="{{ $movie->poster ?: 'https://placehold.co/500x750/222/fff?text=CinemaStar' }}" alt="{{ $movie->title }}"></a><div class="card-body px-2"><div class="small text-brand fw-semibold mb-1">{{ $movie->status === 'upcoming' ? 'SẮP CHIẾU' : 'ĐANG CHIẾU' }}</div><h5 class="fw-bold text-truncate"><a class="text-dark text-decoration-none" href="{{ route('movies.show',$movie) }}">{{ $movie->title }}</a></h5><div class="small text-muted">{{ $movie->duration }} phút · {{ $movie->age_rating }}</div></div></div></div>@empty<div class="col"><div class="alert alert-light">Không tìm thấy phim phù hợp.</div></div>@endforelse</div>
    <div class="mt-4">{{ $movies->links() }}</div>
</div>
@endsection
