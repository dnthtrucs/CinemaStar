<!doctype html>
<html lang="vi">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:20px;background:#ffffff;color:#222222;font-family:Arial,sans-serif;font-size:15px;line-height:1.6">
  <div style="max-width:600px;margin:0 auto">
    <p>Xin chào {{ $customer->name }},</p>
    <p>CinemaStar xác nhận đã nhận thanh toán cho đơn <strong>{{ $booking->code }}</strong>. Thông tin vé của bạn:</p>
    <table role="presentation" style="width:100%;border-collapse:collapse;font-size:14px">
      <tr><td style="padding:6px 0">Mã vé</td><td style="padding:6px 0">{{ $booking->code }}</td></tr>
      <tr><td style="padding:6px 0">Phim</td><td style="padding:6px 0">{{ $showtime->movie->title }}</td></tr>
      <tr><td style="padding:6px 0">Rạp</td><td style="padding:6px 0">{{ $showtime->room->cinema->name }}</td></tr>
      <tr><td style="padding:6px 0">Địa chỉ</td><td style="padding:6px 0">{{ $showtime->room->cinema->address }}</td></tr>
      <tr><td style="padding:6px 0">Suất chiếu</td><td style="padding:6px 0">{{ $showtime->starts_at->format('H:i d/m/Y') }}</td></tr>
      <tr><td style="padding:6px 0">Phòng</td><td style="padding:6px 0">{{ $showtime->room->name }}</td></tr>
      <tr><td style="padding:6px 0">Ghế</td><td style="padding:6px 0">{{ $seatLabels }}</td></tr>
      @include('emails.partials.concessions')
      <tr><td style="padding:6px 0">Đã thanh toán</td><td style="padding:6px 0"><strong>{{ number_format((int) $booking->total_price, 0, ',', '.') }} VNĐ</strong></td></tr>
    </table>
    <p>Đưa mã vé ở trên hoặc mã vạch dưới đây cho nhân viên rạp để kiểm tra và in vé.</p>
    <p><img src="{{ $message->embedData($barcodePng, 'CinemaStar-Barcode-'.$booking->code.'.png', 'image/png') }}" width="420" height="80" alt="Mã vạch vé {{ $booking->code }}" style="display:block;max-width:100%;width:420px;height:auto"></p>
    <p>Bạn cũng có thể đăng nhập CinemaStar và mở mục Vé của tôi để xem lại vé. Vui lòng đến rạp trước giờ chiếu.</p>
    <p>Cảm ơn bạn,<br>CinemaStar</p>
    <p style="font-size:12px;color:#555555">Đây là thư xác nhận cho giao dịch đặt vé của bạn.</p>
  </div>
</body>
</html>
