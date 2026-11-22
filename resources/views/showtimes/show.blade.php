@extends('layouts.app')
@section('title', 'Chọn ghế · '.$showtime->movie->title)
@push('styles')
<style>
    [hidden]{display:none!important}
    .seat-grid{overflow-x:auto;padding-bottom:1rem}.seat-row{display:flex;gap:.45rem;align-items:center;justify-content:center;margin:.45rem 0;min-width:620px}.row-label{width:24px;font-weight:700;color:#777}.seat{width:42px;height:36px;border:1px solid #bbb;border-radius:9px 9px 5px 5px;display:grid;place-items:center;font-size:.75rem;cursor:pointer;background:#fff;transition:.15s}.seat:hover{border-color:#e11d2e}.seat.vip{background:#fff2cc;border-color:#e9b949}.seat.couple{width:88px;background:#ffe0ec;border-color:#f28ab2;color:#a41454;grid-template-columns:1fr;line-height:1.05}.seat.couple small{font-size:.58rem}.seat.booked{background:#d7d7d7;color:#999;border-color:#d7d7d7;cursor:not-allowed}.seat-input{position:absolute;opacity:0;pointer-events:none}.seat-input:checked+.seat{background:#e11d2e;color:#fff;border-color:#e11d2e;transform:translateY(-2px)}.seat-input:checked+.seat.couple{background:#e83e78;border-color:#e83e78}.screen{width:288px;max-width:calc(100% - 80px);height:10px;background:linear-gradient(90deg,transparent,#aaa,transparent);border-radius:100%;box-shadow:0 8px 16px #777;margin:1rem auto 3rem;text-align:center}.screen::after{content:'MÀN HÌNH';position:relative;top:15px;font-size:.7rem;color:#999}

.mobile-booking-info,.mobile-next,.seat-zoom-tools{display:none}
@media(max-width:991.98px){
.booking-page{padding-bottom:calc(var(--booking-bottom-height,260px) + 24px)!important}
#bookingSummary.booking-summary{position:fixed!important;top:auto!important;bottom:0;left:0;right:0;z-index:1040;border-radius:16px 16px 0 0;border:1px solid #e7e7e7;background:#fff;padding:12px 16px calc(12px + env(safe-area-inset-bottom))!important;box-shadow:0 -4px 18px #00000012;gap:5px}
.mobile-booking-info{display:block}.mobile-booking-info>strong{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
#bookingSummary>.summary-heading,#bookingSummary>hr,#bookingSummary>.mb-3,#bookingSummary>.mb-2,#bookingSummary>.small,#bookingSummary>p{display:none!important}
#bookingSummary>.fs-5{font-size:1rem!important;margin:3px 0 7px}
#bookingSummary .btn{min-height:44px;font-size:1rem;padding:10px}
.mobile-next{display:block}.seat-zoom-tools{display:flex;align-items:center;justify-content:flex-end;gap:8px;margin:0 0 12px}
#seatStep .seat-row{min-width:0!important;justify-content:center!important;padding-right:0;gap:calc(3px * var(--seat-zoom,1));margin:5px 0}
#seatStep .seat{width:calc(clamp(20px,5.8vw,34px) * var(--seat-zoom,1));height:calc(29px * var(--seat-zoom,1));font-size:calc(10px * var(--seat-zoom,1));border-radius:5px 5px 3px 3px}
#seatStep .seat.couple{width:calc((clamp(20px,5.8vw,34px) * 2 + 3px) * var(--seat-zoom,1))}
#seatStep .seat.couple small{font-size:calc(7px * var(--seat-zoom,1))}
#seatStep .row-label{width:16px;font-size:11px;flex-shrink:0}
#nextConcessions{display:none!important}
}
</style>
@endpush
@section('content')
<div class="container py-4 booking-page">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-soft p-3 p-md-4">
                <div class="d-flex gap-3 align-items-center mb-4"><img src="{{ $showtime->movie->poster }}" class="rounded" style="width:72px;height:104px;object-fit:cover"><div><div class="text-brand fw-semibold">CHỌN GHẾ</div><h3 class="fw-bold mb-1">{{ $showtime->movie->title }}</h3><div class="text-muted small">{{ $showtime->room->cinema->name }} · {{ $showtime->room->name }} · {{ $showtime->format }}</div><div class="fw-semibold mt-1">{{ $showtime->starts_at->format('H:i · d/m/Y') }}</div></div></div>
                <p id="bookingStep" class="fw-semibold" aria-live="polite">Bước 1/3 · Chọn ghế</p><div class="screen" data-seat-step></div>
                <form id="seatForm" data-showtime-id="{{ $showtime->id }}" action="{{ route('bookings.store',$showtime) }}" method="POST">@csrf
                    <div id="seatStep"><div class="seat-zoom-tools"><span class="small text-muted">Kích thước sơ đồ</span><button type="button" id="seatZoomOut" class="btn btn-sm btn-outline-secondary" aria-label="Thu nhỏ sơ đồ ghế">−</button><button type="button" id="seatZoomIn" class="btn btn-sm btn-outline-secondary" aria-label="Phóng to sơ đồ ghế">+</button></div><p class="seat-scroll-hint small text-muted d-none mb-2">↔ Vuốt ngang sơ đồ để xem tất cả ghế.</p><div class="seat-grid" tabindex="0" role="region" aria-label="Sơ đồ ghế, vuốt ngang để xem thêm">
                        @foreach($seatRows as $row => $seats)<div class="seat-row"><div class="row-label">{{ $row }}</div>@if($row === 'H' && $seats->every(fn ($seat) => $seat->type === 'couple'))@foreach($seats->chunk(2) as $pair)@php($first=$pair->first())@php($second=$pair->values()->get(1))@php($booked=!$second || in_array($first->id,$bookedSeatIds) || in_array($second->id,$bookedSeatIds))<label><input class="seat-input" type="checkbox" name="seats[]" value="{{ $first->id }}" data-label="{{ $first->label }} - {{ $second?->label }} (Ghế đôi)" data-price="{{ ((int)$showtime->base_price + 30000) * 2 + 30000 }}" @disabled($booked)><span class="seat couple {{ $booked?'booked':'' }}"><span>{{ $first->number }}–{{ $second?->number }}</span><small>GHẾ ĐÔI</small></span></label>@endforeach @else @foreach($seats as $seat)@php($booked=in_array($seat->id,$bookedSeatIds))<label><input class="seat-input" type="checkbox" name="seats[]" value="{{ $seat->id }}" data-label="{{ $seat->label }}" data-price="{{ (int)$showtime->base_price+(int)$seat->price_surcharge }}" @disabled($booked)><span class="seat {{ $seat->type }} {{ $booked?'booked':'' }}">{{ $seat->number }}</span></label>@endforeach @endif</div>@endforeach
                    </div>
                    <div class="d-flex justify-content-between mt-3"><strong>Tiền ghế</strong><strong id="seatStepTotal" aria-live="polite">0₫</strong></div><button type="button" id="nextConcessions" class="btn btn-primary w-100 mt-3" disabled>Tiếp theo: chọn bắp nước</button></div><div id="concessionStep" hidden>
                    @if($concessions->isNotEmpty())
                        <section class="mt-4" aria-labelledby="concessionHeading">
                            <h4 id="concessionHeading" class="fw-bold">Bắp nước</h4>
                            <p class="small text-muted">Chọn thêm tối đa 5 loại, mỗi loại tối đa 10 phần. Voucher và điểm áp dụng trên cả vé và combo ở bước thanh toán.</p>
                            <div class="row g-3">
                                @foreach($concessions as $concession)
                                    <div class="col-md-6">
                                        <div class="border rounded-3 p-3 h-100">
                                            @if($concession->imageUrl())<img src="{{ $concession->imageUrl() }}" alt="{{ $concession->name }}" class="rounded mb-2 w-100" style="height:130px;object-fit:cover" loading="lazy">@endif
                                            <h5 class="h6 fw-bold">{{ $concession->name }}</h5>
                                            @if($concession->description)<p class="small text-muted mb-2">{{ $concession->description }}</p>@endif
                                            <div class="d-flex align-items-center justify-content-between gap-2">
                                                <strong>{{ number_format($concession->price,0,',','.') }}₫</strong>
                                                <div class="input-group input-group-sm" style="width:125px">
                                                    <button type="button" class="btn btn-outline-secondary" data-combo-step="-1" data-combo-target="combo{{ $concession->id }}" aria-label="Giảm {{ $concession->name }}">−</button>
                                                    <input id="combo{{ $concession->id }}" class="form-control text-center combo-quantity" type="number" name="concessions[{{ $concession->id }}]" value="{{ old('concessions.'.$concession->id, 0) }}" min="0" max="10" step="1" data-id="{{ $concession->id }}" data-price="{{ $concession->price }}" data-label="{{ $concession->name }}" aria-label="Số lượng {{ $concession->name }}">
                                                    <button type="button" class="btn btn-outline-secondary" data-combo-step="1" data-combo-target="combo{{ $concession->id }}" aria-label="Tăng {{ $concession->name }}">+</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <p id="comboError" class="text-danger small mt-2 mb-0" role="alert" hidden>Chỉ chọn tối đa 5 loại combo.</p>
                        </section>
                    @endif
                    <div class="mt-4"><label class="form-label fw-semibold">Mã giảm giá <span class="text-muted fw-normal">(nếu có)</span></label><input class="form-control" name="voucher_code" maxlength="40" placeholder="Ví dụ: CINEMASTAR10"></div>
                    <button type="button" id="backSeats" class="btn btn-outline-secondary mt-3">Quay lại chọn ghế</button></div>
                </form>
                <div class="d-flex flex-wrap justify-content-center gap-4 small mt-3"><span><i class="d-inline-block border rounded bg-white" style="width:20px;height:16px"></i> Ghế thường</span><span><i class="d-inline-block border rounded" style="width:20px;height:16px;background:#fff2cc"></i> Ghế VIP (+30.000₫)</span><span><i class="d-inline-block border rounded" style="width:28px;height:16px;background:#ffe0ec;border-color:#f28ab2!important"></i> Ghế đôi (2 ghế VIP +30.000₫)</span><span><i class="d-inline-block rounded bg-danger" style="width:20px;height:16px"></i> Đang chọn</span><span><i class="d-inline-block rounded bg-secondary-subtle" style="width:20px;height:16px"></i> Đã đặt</span></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div id="bookingSummary" class="card shadow-soft p-4 sticky-lg-top booking-summary" style="top:90px"><div class="mobile-booking-info"><div class="d-flex justify-content-between align-items-center gap-2"><strong class="text-truncate">{{ $showtime->movie->title }}</strong><a class="text-brand fw-semibold text-nowrap py-2" href="{{ route('movies.show', $showtime->movie) }}#showtimes" aria-label="Đổi suất chiếu phim {{ $showtime->movie->title }}">Đổi suất</a></div><div class="small">{{ $showtime->starts_at->format('H:i') }}–{{ $showtime->ends_at->format('H:i') }} · {{ $showtime->starts_at->format('d/m/Y') }} · {{ $showtime->format }}</div><div class="small text-muted">{{ $showtime->room->cinema->name }}</div></div><h4 class="fw-bold summary-heading">Tóm tắt đặt vé</h4><hr><div class="d-flex justify-content-between mb-2"><span>Giá cơ bản</span><strong>{{ number_format($showtime->base_price,0,',','.') }}₫</strong></div><div class="mb-3"><div class="text-muted small">Ghế đã chọn</div><div id="selectedSeats" class="fw-semibold">Chưa chọn ghế</div></div><div class="mb-3"><div class="text-muted small">Combo đã chọn</div><div id="selectedCombos" class="fw-semibold">Không chọn combo</div></div><div class="d-flex justify-content-between fs-5"><span id="totalLabel">Tiền ghế</span><strong class="text-brand" id="totalPrice">0₫</strong></div><hr><div class="small text-muted mb-3"><i class="bi bi-clock me-1"></i>Ghế được giữ {{ config('cinema.booking_hold_minutes') }} phút sau khi xác nhận.</div><button type="button" id="mobileNext" class="btn btn-primary w-100 mobile-next" disabled>Tiếp tục</button>@auth<button hidden form="seatForm" id="submitSeats" class="btn btn-primary btn-lg w-100" disabled>Tiếp tục thanh toán</button>@else<a hidden id="loginToBook" href="{{ route('login', ['showtime' => $showtime->id]) }}" class="btn btn-primary w-100">Đăng nhập để đặt vé</a><p class="small text-muted text-center mt-2 mb-0">Ghế, combo và mã giảm giá đã chọn sẽ được giữ lại sau khi đăng nhập.</p>@endauth</div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
@vite('resources/js/seat-selection.js')
@endpush
