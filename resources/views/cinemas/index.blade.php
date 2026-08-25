@extends('layouts.app')
@section('title', 'Rạp chiếu')
@section('content')
<div class="container py-5"><div class="text-brand fw-semibold">HỆ THỐNG CINEBOOK</div><h1 class="section-title mb-4">Danh sách rạp chiếu</h1><div class="row g-4">@foreach($cinemas as $cinema)<div class="col-md-6"><div class="card shadow-soft h-100 overflow-hidden"><div class="card-body p-4"><div class="d-flex gap-3"><div class="brand-mark"><i class="bi bi-building"></i></div><div><h4 class="fw-bold">{{ $cinema->name }}</h4><p class="text-muted"><i class="bi bi-geo-alt me-1"></i>{{ $cinema->location }}</p><p>{{ $cinema->description }}</p><div class="small mb-3">{{ $cinema->rooms_count }} phòng · {{ $cinema->phone }}</div><a href="{{ route('cinemas.show',$cinema) }}" class="btn btn-primary">Xem lịch chiếu</a></div></div></div></div></div>@endforeach</div><div class="mt-4">{{ $cinemas->links() }}</div></div>
@endsection
