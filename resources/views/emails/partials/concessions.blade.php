@if($booking->concessions->isNotEmpty())
<tr>
  <td style="padding:12px 0;border-bottom:1px dashed #cbd5e1;color:#52647a;vertical-align:top">Bắp nước<br><i style="font-size:11px">Concessions</i></td>
  <td align="right" style="padding:12px 0;border-bottom:1px dashed #cbd5e1">
    @foreach($booking->concessions as $item)
      <div style="margin:0 0 8px;line-height:1.5">
        <div style="font-weight:700">{{ $item->pivot->name }} × {{ $item->pivot->quantity }}</div>
        <div style="font-size:12px;color:#52647a">{{ number_format($item->pivot->unit_price, 0, ',', '.') }}₫/phần</div>
        <div style="font-weight:700">{{ number_format($item->pivot->unit_price * $item->pivot->quantity, 0, ',', '.') }}₫</div>
      </div>
    @endforeach
    <div style="font-size:11px;color:#52647a">Giá trước ưu đãi / Before discounts</div>
  </td>
</tr>
@endif
