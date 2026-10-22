{{-- Khung chung cho các trang tài khoản: đăng nhập, đăng ký, quên/đặt lại mật khẩu, xác thực email. --}}
@extends('layouts.app')

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="@yield('auth_width', 'col-md-6 col-lg-5')">
                <div class="card shadow-soft p-4 p-md-5">
                    <div class="text-center mb-4">
                        <img src="{{ asset('images/cinemastar-logo.svg') }}" alt="CinemaStar" style="width:76px;height:76px;object-fit:contain" class="d-block mx-auto mb-3">
                        <h2 class="fw-bold">@yield('heading')</h2>
                        @hasSection('subheading')
                            <p class="text-muted">@yield('subheading')</p>
                        @endif
                    </div>

                    @yield('form')
                </div>
            </div>
        </div>
    </div>
@endsection
