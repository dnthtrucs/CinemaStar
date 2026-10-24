@if($booking->concessions->isNotEmpty())
<section class="my-3" aria-label="Bắp nước trong đơn">
    <h5 class="fw-bold">Bắp nước</h5>
    <ul class="list-unstyled mb-2">
        @foreach($booking->concessions as $item)
            <li class="mb-1">{{ $item->name }} × {{ $item->pivot->quantity }} — {{ number_format($item->pivot->unit_price, 0, ',', '.') }}₫/phần · <strong>{{ number_format($item->pivot->unit_price * $item->pivot->quantity, 0, ',', '.') }}₫</strong></li>
        @endforeach
    </ul>
    <div class="small">Tiền vé: {{ number_format(max(0, (int) $booking->subtotal - (int) $booking->concessions_total), 0, ',', '.') }}₫ · Bắp nước: {{ number_format($booking->concessions_total, 0, ',', '.') }}₫ (trước ưu đãi)</div>
</section>
@endif
