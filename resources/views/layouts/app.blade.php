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
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('home') }}">
            <img class="navbar-logo" src="{{ asset('images/cinemastar-logo.svg') }}" alt=""><span class="navbar-wordmark">CINEMASTAR</span>
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
                    <a class="btn btn-outline-light btn-sm" href="{{ route('support.index') }}"><i class="bi bi-question-circle me-1"></i>Hỗ trợ</a>
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
@stack('scripts')
</body>
</html>
