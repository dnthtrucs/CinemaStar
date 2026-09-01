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
        :root { --brand:#a51c30; --brand-dark:#650d19; --brand-light:#fae8e4; --gold:#f3c969; --gold-dark:#c9972e; --ink:#241217; --muted:#78666a; --surface:#f9f4f1; --night:#1b0a10; }
        body { font-family:'Be Vietnam Pro',sans-serif; color:var(--ink); background:linear-gradient(180deg,#fff9f6 0,var(--surface) 42rem); }
        a { color:var(--brand); }
        a:hover { color:var(--brand-dark); }
        .navbar.bg-dark { background:linear-gradient(100deg,#210b12 0%,#420f1c 55%,#260b12 100%)!important; border-bottom:2px solid rgba(243,201,105,.6); }
        .navbar-brand { font-weight:800; letter-spacing:-1px; color:#fff8e8!important; }
        .brand-mark { width:34px; height:34px; display:inline-grid; place-items:center; border-radius:10px; background:linear-gradient(135deg,var(--gold),#d89c2e); color:#4a0c18; box-shadow:0 3px 10px rgba(0,0,0,.25); }
        .btn-primary { --bs-btn-bg:var(--brand); --bs-btn-border-color:var(--brand); --bs-btn-hover-bg:var(--brand-dark); --bs-btn-hover-border-color:var(--brand-dark); --bs-btn-active-bg:var(--brand-dark); --bs-btn-active-border-color:var(--brand-dark); }
        .btn-outline-light:hover { color:#4a0c18; background:var(--gold); border-color:var(--gold); }
        .btn-danger { --bs-btn-bg:var(--brand-dark); --bs-btn-border-color:var(--brand-dark); --bs-btn-hover-bg:#430812; --bs-btn-hover-border-color:#430812; }
        .form-control:focus, .form-select:focus { border-color:#d79a68; box-shadow:0 0 0 .25rem rgba(165,28,48,.14); }
        .text-brand { color:var(--brand)!important; }
        .text-gold { color:var(--gold)!important; }
        .bg-brand { background:var(--brand)!important; }
        .card { border:1px solid rgba(165,28,48,.08); border-radius:18px; }
        .shadow-soft { box-shadow:0 12px 40px rgba(58,15,26,.09); }
        .movie-poster { aspect-ratio:2/3; object-fit:cover; width:100%; border-radius:16px; background:#eadeda; }
        .movie-card { transition:.2s ease; height:100%; }
        .movie-card:hover { transform:translateY(-5px); box-shadow:0 18px 40px rgba(73,12,27,.16); }
        .section-title { font-weight:800; letter-spacing:-.6px; }
        .status-dot { width:8px; height:8px; border-radius:50%; display:inline-block; margin-right:6px; }
        .badge.text-bg-danger { background-color:var(--brand)!important; }
        .admin-nav.bg-dark { background:linear-gradient(100deg,#2c0b14,#510f21)!important; border:1px solid rgba(243,201,105,.22); }
        .admin-nav .nav-link.active, .admin-nav .nav-link:hover { color:#fff8e8!important; background:rgba(243,201,105,.18); }
        .ticket-qr-card { border-color:rgba(165,28,48,.4)!important; border-style:dashed!important; background:#fff; }
        .ticket-qr-wrap { min-height:220px; display:grid; place-items:center; position:relative; }
        .ticket-qr { display:none; width:220px!important; height:220px!important; max-width:100%; }
        .ticket-qr.is-ready { display:block; }
        .ticket-qr-loading { position:absolute; inset:0; display:grid; place-items:center; }
        footer { background:linear-gradient(105deg,#17070c,#3d0c18); border-top:2px solid rgba(243,201,105,.55); color:#d9c9c5; }
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
                    @elseif(auth()->user()->isStaff())
                        <li class="nav-item"><a class="nav-link text-warning" href="{{ route('staff.tickets.index') }}"><i class="bi bi-qr-code-scan me-1"></i>Check-in</a></li>
                    @endif
                @endauth
            </ul>
            <div class="d-flex align-items-center gap-2 mt-3 mt-lg-0">
                @auth
                    <a class="btn btn-outline-light btn-sm" href="{{ route('dashboard') }}"><i class="bi bi-speedometer2 me-1"></i>{{ auth()->user()->name }}</a>
                    <a class="btn btn-outline-light btn-sm" href="{{ route('profile.edit') }}" title="Hồ sơ"><i class="bi bi-gear"></i></a>
                    <form action="{{ route('logout') }}" method="POST">@csrf<button class="btn btn-danger btn-sm" type="submit">Đăng xuất</button></form>
                @else
                    <a class="btn btn-outline-light btn-sm" href="{{ route('support.index') }}"><i class="bi bi-question-circle me-1"></i>Hỗ trợ</a>
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
