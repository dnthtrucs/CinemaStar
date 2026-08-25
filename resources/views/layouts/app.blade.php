<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'CinemaStar')) · Đặt vé xem phim</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --brand:#e11d2e; --brand-dark:#a90f1d; --ink:#171717; --muted:#737373; --surface:#f7f6f3; }
        body { font-family:'Be Vietnam Pro',sans-serif; color:var(--ink); background:var(--surface); }
        .navbar-brand { font-weight:800; letter-spacing:-1px; }
        .brand-mark { width:34px; height:34px; display:inline-grid; place-items:center; border-radius:10px; background:var(--brand); color:#fff; }
        .btn-primary { --bs-btn-bg:var(--brand); --bs-btn-border-color:var(--brand); --bs-btn-hover-bg:var(--brand-dark); --bs-btn-hover-border-color:var(--brand-dark); }
        .text-brand { color:var(--brand)!important; }
        .bg-brand { background:var(--brand)!important; }
        .card { border:0; border-radius:18px; }
        .shadow-soft { box-shadow:0 12px 40px rgba(23,23,23,.08); }
        .movie-poster { aspect-ratio:2/3; object-fit:cover; width:100%; border-radius:16px; background:#ddd; }
        .movie-card { transition:.2s ease; height:100%; }
        .movie-card:hover { transform:translateY(-5px); box-shadow:0 16px 36px rgba(23,23,23,.13); }
        .section-title { font-weight:800; letter-spacing:-.6px; }
        .status-dot { width:8px; height:8px; border-radius:50%; display:inline-block; margin-right:6px; }
        .admin-nav .nav-link.active, .admin-nav .nav-link:hover { color:#fff!important; background:rgba(255,255,255,.12); }
        .ticket-qr-card { border-style:dashed!important; background:#fff; }
        .ticket-qr-wrap { min-height:220px; display:grid; place-items:center; position:relative; }
        .ticket-qr { display:none; width:220px!important; height:220px!important; max-width:100%; }
        .ticket-qr.is-ready { display:block; }
        .ticket-qr-loading { position:absolute; inset:0; display:grid; place-items:center; }
        footer { background:#111; color:#aaa; }
        @media (max-width:767px) { .display-4 { font-size:2.1rem; } }
    </style>
    @stack('styles')
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('home') }}">
            <span class="brand-mark"><i class="bi bi-film"></i></span> CINEMASTAR
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto ms-lg-4">
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">Trang chủ</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('movies.*') ? 'active' : '' }}" href="{{ route('movies.index') }}">Phim</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('cinemas.*') ? 'active' : '' }}" href="{{ route('cinemas.index') }}">Rạp chiếu</a></li>
                @auth
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('bookings.*') ? 'active' : '' }}" href="{{ route('bookings.index') }}">Vé của tôi</a></li>
                    @if(auth()->user()->isAdmin())
                        <li class="nav-item"><a class="nav-link text-warning" href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2 me-1"></i>Quản trị</a></li>
                    @endif
                @endauth
            </ul>
            <div class="d-flex align-items-center gap-2 mt-3 mt-lg-0">
                @auth
                    <a class="btn btn-outline-light btn-sm" href="{{ route('dashboard') }}"><i class="bi bi-speedometer2 me-1"></i>{{ auth()->user()->name }}</a>
                    <a class="btn btn-outline-light btn-sm" href="{{ route('profile.edit') }}" title="Hồ sơ"><i class="bi bi-gear"></i></a>
                    <form action="{{ route('logout') }}" method="POST">@csrf<button class="btn btn-danger btn-sm" type="submit">Đăng xuất</button></form>
                @else
                    <a class="btn btn-outline-light btn-sm" href="{{ route('login') }}">Đăng nhập</a>
                    <a class="btn btn-primary btn-sm" href="{{ route('register') }}">Đăng ký</a>
                @endauth
            </div>
        </div>
    </div>
</nav>

<main class="min-vh-100">
    <div class="container pt-3">
        @if(session('success'))<div class="alert alert-success alert-dismissible fade show shadow-sm"><i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif
        @if(session('status'))<div class="alert alert-success alert-dismissible fade show shadow-sm"><i class="bi bi-check-circle-fill me-2"></i>Thao tác thành công.<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif
        @if(session('error'))<div class="alert alert-danger alert-dismissible fade show shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif
        @if($errors->any())
            <div class="alert alert-danger shadow-sm"><strong>Vui lòng kiểm tra lại:</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
    </div>
    @yield('content')
</main>

<footer class="mt-5 py-5">
    <div class="container d-flex flex-column flex-md-row justify-content-between gap-3">
        <div><strong class="text-white">CINEMASTAR</strong><div class="small mt-2">Hệ thống quản lý và đặt vé rạp chiếu phim.</div></div>
        <div class="small">  Laravel 11 · © {{ date('Y') }}</div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/cinemastar-ticket-qr.js') }}" defer></script>
@stack('scripts')
</body>
</html>
