@extends('layouts.app')
@section('title','Đổi suất chiếu')
@section('content')
<div class="container py-4" style="max-width:900px"><h1 class="h3">Đổi suất — {{ $booking->showtime->movie->title }}</h1>
<div class="alert alert-info">Đổi cùng phim, cùng rạp, giữ số vị trí và combo. Suất cũ và mới phải còn ít nhất 12 tiếng; vé chưa dùng và chưa yêu cầu hoàn. Giá ưu đãi cũ được giữ nguyên, không cộng thêm điểm cho lần đổi. Chênh lệch cần thu bù/hoàn theo chứng từ được quản trị xác nhận. Vé cũ còn hiệu lực trong khi chờ; ghế mới chưa được giữ.</div>
<p>Đơn {{ $booking->code }} · {{ $booking->quantity }} vị trí · Tổng cũ {{ number_format($booking->total_price) }}₫</p>
@if(!$eligible)<div class="alert alert-warning">Vé hiện tại không đủ điều kiện lập yêu cầu mới. Bạn vẫn có thể xem lịch sử và rút yêu cầu đang chờ.</div>@elseif($hasPending)<div class="alert alert-info">Bạn có yêu cầu đang chờ. Có thể rút yêu cầu bên dưới để chọn một suất khác; vé hiện tại không thay đổi.</div>@endif
@if($eligible && !$hasPending)
<form method="GET"><label for="target" class="form-label">Chọn suất mới</label><select id="target" name="target" class="form-select" onchange="this.form.submit()"><option value="">Chọn suất</option>@foreach($targets as $option)<option value="{{ $option->id }}" @selected($target?->id===$option->id)>{{ $option->starts_at->format('H:i d/m/Y') }} · {{ $option->room->name }} · {{ $option->format }} · cơ bản {{ number_format($option->base_price) }}₫</option>@endforeach</select><noscript><button class="btn btn-secondary mt-2">Xem ghế</button></noscript></form>
@if($target) @php($unavailable=$target->tickets()->pluck('seat_id')->all())
<form class="card p-3 mt-3" method="POST" action="{{ route('exchanges.store',$booking) }}" id="exchangeForm" data-old="{{ (int)$booking->total_price }}" data-combos="{{ (int)$booking->concessions_total }}" data-discount="{{ (int)$booking->discount + $booking->points_used*1000 }}">@csrf<input type="hidden" name="target" value="{{ $target->id }}"><fieldset><legend class="h6">Chọn đủ {{ $booking->quantity }} vị trí (ghế đôi phải chọn cả cặp)</legend><div class="d-flex flex-wrap gap-3">@foreach($target->room->seats->sortBy(fn($s)=>$s->row.str_pad($s->number,3,'0',STR_PAD_LEFT)) as $seat)<label><input type="checkbox" name="seats[]" value="{{ $seat->id }}" data-price="{{ (int)$target->base_price + (int)$seat->price_surcharge }}" @disabled(!$seat->is_active || in_array($seat->id,$unavailable))> {{ $seat->label }} {{ $seat->type==='couple'?'(đôi)':'' }} {{ $seat->is_accessible?'♿':'' }}</label>@endforeach</div></fieldset><p id="exchangeQuote" class="fw-bold mt-3" role="status">Chọn ghế để xem giá dự kiến.</p><label for="exchangeReason">Lý do đổi</label><textarea id="exchangeReason" name="reason" class="form-control" maxlength="1000" required></textarea><button class="btn btn-primary mt-3">Gửi yêu cầu đổi suất</button></form>
@endif
@endif
<h2 class="h5 mt-4">Lịch sử yêu cầu</h2>@forelse($requests as $item)
<article class="card p-3 mb-3"><div class="d-flex flex-wrap justify-content-between gap-2"><strong>Yêu cầu #{{ $item->id }} · {{ $item->status_label }}</strong><small>{{ $item->created_at->format('H:i d/m/Y') }}</small></div>
<p class="mt-2">Suất cũ: {{ $item->snapshot['from_time'] ?? $item->original->starts_at->format('H:i d/m/Y') }} · {{ $item->original->room->name }}<br>Suất muốn đổi: {{ $item->snapshot['to_time'] ?? $item->target->starts_at->format('H:i d/m/Y') }} · {{ $item->target->room->name }}<br>Tổng cũ {{ number_format($item->snapshot['old_total']) }}₫ → Tổng mới {{ number_format($item->snapshot['total']) }}₫ · {{ $item->difference>=0?'Cần bù':'Cần hoàn' }} {{ number_format(abs($item->difference)) }}₫</p>
@if(isset($item->snapshot['new_seats']))<p>Ghế yêu cầu: {{ collect($item->snapshot['new_seats'])->pluck('seat')->join(', ') }}</p>@endif
<p>Lý do: {{ $item->reason }}</p>@if($item->resolution)<p class="mb-1">Kết quả: {{ $item->resolution }}</p><small class="text-muted">{{ $item->handled_at?->format('H:i d/m/Y') }}</small>@endif
@if($item->status==='pending')<p class="small text-muted">Ghế mới chưa được giữ. Nếu đã thu bù/hoàn bên ngoài, liên hệ rạp để đối soát; rút yêu cầu không tự hoàn tiền.</p><form method="POST" action="{{ route('exchanges.withdraw',$item) }}">@csrf<button class="btn btn-outline-danger btn-sm">Rút yêu cầu, giữ vé hiện tại</button></form>@endif
</article>
@empty<p>Chưa có yêu cầu.</p>@endforelse
<a href="{{ route('bookings.show',$booking) }}">Quay lại vé</a></div>
@endsection
@push('scripts')@vite('resources/js/exchange.js')@endpush
