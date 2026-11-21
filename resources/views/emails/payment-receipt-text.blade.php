CinemaStar - Xác nhận thanh toán

Xin chào {!! $customer->name !!},

Thanh toán cho đơn {!! $booking->code !!} đã hoàn tất.

Mã vé: {!! $booking->code !!}
Phim: {!! $showtime->movie->title !!}
Rạp: {!! $showtime->room->cinema->name !!}
Phòng: {!! $showtime->room->name !!}
Suất chiếu: {{ $showtime->starts_at->format('H:i d/m/Y') }}
Ghế: {!! $seatLabels !!}
@if($booking->concessions->isNotEmpty())
Bắp nước (giá trước ưu đãi):
@foreach($booking->concessions as $item)
{!! $item->pivot->name !!} x {{ $item->pivot->quantity }}: {{ number_format($item->pivot->unit_price * $item->pivot->quantity, 0, ',', '.') }} VNĐ
@endforeach
@endif
Tổng thanh toán: {{ number_format((int) $booking->total_price, 0, ',', '.') }} VNĐ

Bạn có thể đăng nhập CinemaStar, mở Vé của tôi để xem vé và mã QR.
Đưa mã vé hoặc mã QR cho nhân viên rạp để kiểm tra và in vé.
Vui lòng đến rạp trước giờ chiếu.

Đây là email xác nhận cho đơn đặt vé của bạn tại CinemaStar.
