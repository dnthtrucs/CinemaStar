@extends('layouts.app')
@section('title', 'Ưu đãi CinemaStar')
@section('content')
<div class="container py-4">
    <h1 class="section-title">Ưu đãi CinemaStar</h1>
    <p class="text-muted">Chọn mã phù hợp và nhập ở bước đặt vé. Mỗi tài khoản sử dụng mỗi mã một lần; mỗi đơn áp dụng một voucher.</p>
    <div class="row g-3">
    @forelse($vouchers as $voucher)
        <div class="col-md-6 col-xl-4"><article class="card h-100 shadow-soft p-4">
            <h2 class="h5">{{ $voucher->name }}</h2>
            <p class="h3 text-primary">Giảm {{ $voucher->type === 'percent' ? $voucher->value.'%' : number_format($voucher->value,0,',','.').'₫' }}</p>
            <p>Đơn từ {{ number_format($voucher->min_order,0,',','.') }}₫</p>
            <p>Mã: <strong class="user-select-all">{{ $voucher->code }}</strong></p>
            <p class="small text-muted mb-2">{{ $voucher->starts_at?->format('d/m/Y H:i') ?? 'Hiện tại' }} – {{ $voucher->ends_at?->format('d/m/Y H:i') ?? 'Không giới hạn' }}</p>
            @if($voucher->usage_limit)<p class="small">Giới hạn {{ $voucher->usage_limit }} lượt. Lượt còn lại được kiểm tra khi đặt vé.</p>@endif
            @if($voucher->starts_at?->isFuture())<span class="badge text-bg-secondary align-self-start">Sắp áp dụng</span>
            @else<a class="btn btn-primary mt-auto" href="{{ route('movies.index') }}">Chọn phim đặt vé</a>@endif
        </article></div>
    @empty
        <p>Hiện chưa có chương trình ưu đãi. Vui lòng quay lại sau.</p>
    @endforelse
    </div>
    <div class="mt-4">{{ $vouchers->links() }}</div>
</div>
@endsection
