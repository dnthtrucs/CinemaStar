@extends('layouts.app')
@section('title', 'Chọn ghế · '.$showtime->movie->title)
@push('styles')
<style>
    .seat-grid{overflow-x:auto;padding-bottom:1rem}.seat-row{display:flex;gap:.45rem;align-items:center;justify-content:center;margin:.45rem 0;min-width:620px}.row-label{width:24px;font-weight:700;color:#777}.seat{width:42px;height:36px;border:1px solid #bbb;border-radius:9px 9px 5px 5px;display:grid;place-items:center;font-size:.75rem;cursor:pointer;background:#fff;transition:.15s}.seat:hover{border-color:#e11d2e}.seat.vip{background:#fff2cc;border-color:#e9b949}.seat.couple{width:88px;background:#ffe0ec;border-color:#f28ab2;color:#a41454;grid-template-columns:1fr;line-height:1.05}.seat.couple small{font-size:.58rem}.seat.booked{background:#d7d7d7;color:#999;border-color:#d7d7d7;cursor:not-allowed}.seat-input{position:absolute;opacity:0;pointer-events:none}.seat-input:checked+.seat{background:#e11d2e;color:#fff;border-color:#e11d2e;transform:translateY(-2px)}.seat-input:checked+.seat.couple{background:#e83e78;border-color:#e83e78}.screen{height:10px;background:linear-gradient(90deg,transparent,#aaa,transparent);border-radius:100%;box-shadow:0 8px 16px #777;margin:1rem auto 3rem;max-width:620px;text-align:center}.screen::after{content:'MÀN HÌNH';position:relative;top:15px;font-size:.7rem;color:#999}
</style>
@endpush
@section('content')
<div class="container py-4">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-soft p-3 p-md-4">
                <div class="d-flex gap-3 align-items-center mb-4"><img src="{{ $showtime->movie->poster }}" class="rounded" style="width:72px;height:104px;object-fit:cover"><div><div class="text-brand fw-semibold">CHỌN GHẾ</div><h3 class="fw-bold mb-1">{{ $showtime->movie->title }}</h3><div class="text-muted small">{{ $showtime->room->cinema->name }} · {{ $showtime->room->name }} · {{ $showtime->format }}</div><div class="fw-semibold mt-1">{{ $showtime->starts_at->format('H:i · d/m/Y') }}</div></div></div>
                <div class="screen"></div>
                <form id="seatForm" action="{{ route('bookings.store',$showtime) }}" method="POST">@csrf
                    <div class="seat-grid">
                        @foreach($seatRows as $row => $seats)<div class="seat-row"><div class="row-label">{{ $row }}</div>@if($row === 'H' && $seats->every(fn ($seat) => $seat->type === 'couple'))@foreach($seats->chunk(2) as $pair)@php($first=$pair->first())@php($second=$pair->get(1))@php($booked=!$second || in_array($first->id,$bookedSeatIds) || in_array($second->id,$bookedSeatIds))<label><input class="seat-input" type="checkbox" name="seats[]" value="{{ $first->id }}" data-label="{{ $first->label }} - {{ $second?->label }} (Ghế đôi)" data-price="{{ ((int)$showtime->base_price * 2) + 30000 }}" @disabled($booked)><span class="seat couple {{ $booked?'booked':'' }}"><span>{{ $first->number }}–{{ $second?->number }}</span><small>GHẾ ĐÔI</small></span></label>@endforeach @else @foreach($seats as $seat)@php($booked=in_array($seat->id,$bookedSeatIds))<label><input class="seat-input" type="checkbox" name="seats[]" value="{{ $seat->id }}" data-label="{{ $seat->label }}" data-price="{{ (int)$showtime->base_price+(int)$seat->price_surcharge }}" @disabled($booked)><span class="seat {{ $seat->type }} {{ $booked?'booked':'' }}">{{ $seat->number }}</span></label>@endforeach @endif</div>@endforeach
                    </div>
                    <div class="mt-4"><label class="form-label fw-semibold">Mã giảm giá <span class="text-muted fw-normal">(nếu có)</span></label><input class="form-control" name="voucher_code" maxlength="40" placeholder="Ví dụ: CINEMASTAR10"></div>
                </form>
                <div class="d-flex flex-wrap justify-content-center gap-4 small mt-3"><span><i class="d-inline-block border rounded bg-white" style="width:20px;height:16px"></i> Ghế thường</span><span><i class="d-inline-block border rounded" style="width:20px;height:16px;background:#fff2cc"></i> Ghế VIP (+30.000₫)</span><span><i class="d-inline-block border rounded" style="width:28px;height:16px;background:#ffe0ec;border-color:#f28ab2!important"></i> Ghế đôi (+30.000₫/cặp)</span><span><i class="d-inline-block rounded bg-danger" style="width:20px;height:16px"></i> Đang chọn</span><span><i class="d-inline-block rounded bg-secondary-subtle" style="width:20px;height:16px"></i> Đã đặt</span></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-soft p-4 sticky-lg-top" style="top:90px"><h4 class="fw-bold">Tóm tắt đặt vé</h4><hr><div class="d-flex justify-content-between mb-2"><span>Giá cơ bản</span><strong>{{ number_format($showtime->base_price,0,',','.') }}₫</strong></div><div class="mb-3"><div class="text-muted small">Ghế đã chọn</div><div id="selectedSeats" class="fw-semibold">Chưa chọn ghế</div></div><div class="d-flex justify-content-between fs-5"><span>Tạm tính</span><strong class="text-brand" id="totalPrice">0₫</strong></div><hr><div class="small text-muted mb-3"><i class="bi bi-clock me-1"></i>Ghế được giữ {{ config('cinema.booking_hold_minutes') }} phút sau khi xác nhận.</div>@auth<button form="seatForm" id="submitSeats" class="btn btn-primary btn-lg w-100" disabled>Tiếp tục thanh toán</button>@else<a id="loginToBook" href="{{ route('login', ['showtime' => $showtime->id]) }}" class="btn btn-primary w-100">Đăng nhập để đặt vé</a><p class="small text-muted text-center mt-2 mb-0">Ghế và mã giảm giá đã chọn sẽ được giữ lại sau khi đăng nhập.</p>@endauth</div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
const inputs=[...document.querySelectorAll('.seat-input:not(:disabled)')];
const selected=document.getElementById('selectedSeats');
const total=document.getElementById('totalPrice');
const submit=document.getElementById('submitSeats');
const voucherInput=document.querySelector('[name="voucher_code"]');
const savedSeatsKey='cinemastar:showtime:{{ $showtime->id }}:seats';
const savedVoucherKey='cinemastar:showtime:{{ $showtime->id }}:voucher';

try {
    const savedSeatIds=JSON.parse(localStorage.getItem(savedSeatsKey) || '[]').map(String);
    inputs.forEach(input=>input.checked=savedSeatIds.includes(input.value));
    if (voucherInput && !voucherInput.value) voucherInput.value=localStorage.getItem(savedVoucherKey) || '';
} catch (error) {
    localStorage.removeItem(savedSeatsKey);
}

function update(){
    const checked=inputs.filter(i=>i.checked);
    selected.textContent=checked.length?checked.map(i=>i.dataset.label).join(', '):'Chưa chọn ghế';
    total.textContent=new Intl.NumberFormat('vi-VN').format(checked.reduce((sum,i)=>sum+Number(i.dataset.price),0))+'₫';
    localStorage.setItem(savedSeatsKey,JSON.stringify(checked.map(i=>i.value)));
    if(submit)submit.disabled=!checked.length;
}

inputs.forEach(i=>i.addEventListener('change',update));
if(voucherInput) voucherInput.addEventListener('input',()=>localStorage.setItem(savedVoucherKey,voucherInput.value));
update();
</script>
@endpush
