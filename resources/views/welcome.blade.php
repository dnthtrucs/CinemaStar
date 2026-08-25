@extends('layouts.app')
@section('title', 'Trang chủ')
@section('content')
@if($banners->isNotEmpty())
<section class="home-banner mb-5">
    <div id="cinemaStarBanner" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="5500">
        <div class="carousel-indicators">
            @foreach($banners as $banner)
                <button type="button" data-bs-target="#cinemaStarBanner" data-bs-slide-to="{{ $loop->index }}" class="{{ $loop->first ? 'active' : '' }}" @if($loop->first) aria-current="true" @endif aria-label="Banner {{ $loop->iteration }}"></button>
            @endforeach
        </div>
        <div class="carousel-inner">
            @foreach($banners as $banner)
                <div class="carousel-item {{ $loop->first ? 'active' : '' }}">
                    <img src="{{ asset('storage/'.$banner->image_path) }}" class="d-block w-100" alt="{{ $banner->title }}">
                    <div class="banner-overlay"></div>
                    <div class="carousel-caption text-start">
                        <div class="container px-0">
                            <div class="col-lg-7">
                                <h1 class="display-5 fw-bold">{{ $banner->title }}</h1>
                                @if($banner->subtitle)<p class="lead mb-4">{{ $banner->subtitle }}</p>@endif
                                @if($banner->button_url)<a href="{{ $banner->button_url }}" class="btn btn-primary btn-lg px-4">{{ $banner->button_label ?: 'Đặt vé ngay' }} <i class="bi bi-arrow-right ms-2"></i></a>@endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        @if($banners->count() > 1)
            <button class="carousel-control-prev" type="button" data-bs-target="#cinemaStarBanner" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span><span class="visually-hidden">Trước</span></button>
            <button class="carousel-control-next" type="button" data-bs-target="#cinemaStarBanner" data-bs-slide="next"><span class="carousel-control-next-icon"></span><span class="visually-hidden">Tiếp</span></button>
        @endif
    </div>
</section>
@else
<section class="bg-dark text-white py-5 mb-5">
    <div class="container py-lg-5">
        <div class="row align-items-center g-5"><div class="col-lg-7">
            <span class="badge rounded-pill bg-brand px-3 py-2 mb-3">TRẢI NGHIỆM ĐIỆN ẢNH</span>
            <h1 class="display-4 fw-bold mb-3">Chọn phim hay.<br><span class="text-danger">Đặt ghế trong vài giây.</span></h1>
            <p class="lead text-white-50 mb-4">Lịch chiếu cập nhật theo thời gian thực, chọn đúng ghế yêu thích và thanh toán an toàn qua MoMo hoặc VNPAY.</p>
            <a href="#lich-chieu" class="btn btn-primary btn-lg px-4">Đặt vé ngay <i class="bi bi-arrow-right ms-2"></i></a>
            <a href="{{ route('movies.index') }}" class="btn btn-outline-light btn-lg px-4 ms-2">Xem tất cả phim</a>
        </div></div>
    </div>
</section>
@endif

@push('styles')
<style>
.home-banner .carousel-item { height:clamp(280px,42vw,560px); background:#171717; }
.home-banner .carousel-item img { height:100%; object-fit:cover; }
.home-banner .banner-overlay { position:absolute; inset:0; background:linear-gradient(90deg,rgba(0,0,0,.7) 0%,rgba(0,0,0,.28) 50%,rgba(0,0,0,.05) 100%); }
.home-banner .carousel-caption { right:8%; left:8%; bottom:14%; z-index:2; text-shadow:0 2px 12px rgba(0,0,0,.75); }
.home-banner .carousel-indicators { z-index:3; margin-bottom:1rem; }
.home-banner .carousel-indicators button { width:9px; height:9px; border-radius:50%; margin:0 5px; }
@media (max-width: 767px) { .home-banner .carousel-item { height:360px; } .home-banner .carousel-caption { bottom:16%; left:12%; right:12%; } .home-banner h1 { font-size:1.9rem; } .home-banner .lead { font-size:1rem; } }
</style>
@endpush

<section class="container mb-5">
    <div class="d-flex justify-content-between align-items-end mb-4"><div><div class="text-brand fw-semibold">ĐANG CHIẾU</div><h2 class="section-title mb-0">Phim nổi bật</h2></div><a href="{{ route('movies.index') }}" class="text-decoration-none">Xem tất cả <i class="bi bi-chevron-right"></i></a></div>
    <div class="row g-4">
        @forelse($featuredMovies as $movie)
            <div class="col-6 col-md-4 col-lg-3">
                <div class="card movie-card p-2 shadow-soft">
                    <a href="{{ route('movies.show', $movie) }}"><img class="movie-poster" src="{{ $movie->poster ?: 'https://placehold.co/500x750/222/fff?text=CinemaStar' }}" alt="{{ $movie->title }}"></a>
                    <div class="card-body px-2 pb-2">
                        <div class="d-flex gap-2 mb-2"><span class="badge text-bg-danger">{{ $movie->age_rating }}</span><span class="small text-muted">{{ $movie->duration }} phút</span></div>
                        <h5 class="fw-bold text-truncate"><a class="text-dark text-decoration-none" href="{{ route('movies.show', $movie) }}">{{ $movie->title }}</a></h5>
                        <div class="small text-muted text-truncate">{{ $movie->genre }}</div>
                    </div>
                </div>
            </div>
        @empty <div class="col"><div class="alert alert-light">Chưa có phim đang chiếu.</div></div>@endforelse
    </div>
</section>

<section id="lich-chieu" class="container mb-5">
    <div class="mb-4"><div class="text-brand fw-semibold">7 NGÀY TỚI</div><h2 class="section-title">Lịch chiếu gần nhất</h2></div>
    <div class="card shadow-soft overflow-hidden">
        <div class="list-group list-group-flush">
            @forelse($showtimes as $showtime)
                <div class="list-group-item p-3 p-lg-4 d-flex flex-column flex-md-row align-items-md-center gap-3">
                    <div style="min-width:115px"><div class="fw-bold fs-5">{{ $showtime->starts_at->format('H:i') }}</div><div class="small text-muted">{{ $showtime->starts_at->format('d/m/Y') }}</div></div>
                    <div class="flex-grow-1"><a class="fw-bold text-dark text-decoration-none" href="{{ route('movies.show', $showtime->movie) }}">{{ $showtime->movie->title }}</a><div class="small text-muted mt-1">{{ $showtime->room->cinema->name }} · {{ $showtime->room->name }} · {{ $showtime->format }}</div></div>
                    <div class="fw-semibold">Từ {{ number_format($showtime->base_price, 0, ',', '.') }}₫</div>
                    <a href="{{ route('showtimes.show', $showtime) }}" class="btn btn-primary">Chọn ghế</a>
                </div>
            @empty <div class="list-group-item p-4 text-muted">Chưa có lịch chiếu.</div>@endforelse
        </div>
    </div>
</section>

<section class="container mb-5">
    <div class="mb-4"><div class="text-brand fw-semibold">HỆ THỐNG RẠP</div><h2 class="section-title">Rạp gần bạn</h2></div>
    <div class="row g-3">@foreach($cinemas as $cinema)<div class="col-md-6 col-lg-4"><a href="{{ route('cinemas.show', $cinema) }}" class="card p-4 h-100 text-decoration-none text-dark shadow-soft"><div class="d-flex gap-3"><div class="brand-mark flex-shrink-0"><i class="bi bi-geo-alt"></i></div><div><h5 class="fw-bold">{{ $cinema->name }}</h5><div class="text-muted small">{{ $cinema->location }}</div><div class="small text-brand mt-2">{{ $cinema->rooms_count }} phòng chiếu</div></div></div></a></div>@endforeach</div>
</section>
@endsection
