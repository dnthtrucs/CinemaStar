<!doctype html>
<html lang="vi">
<body style="margin:0;background:#f1f3f5;font-family:Arial,sans-serif;color:#20242a">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:28px 12px"><tr><td align="center">
    <table role="presentation" width="480" cellspacing="0" cellpadding="0" style="max-width:480px;background:#fff;border-radius:8px;overflow:hidden">
      <tr><td align="center" style="padding:26px 24px 18px;border-bottom:4px dotted #eef1f4">
        <div style="font-size:25px;font-weight:700;color:#d71920;letter-spacing:.4px">CINEMASTAR</div>
        <div style="font-size:13px;color:#667085;margin-top:7px">VÉ ĐIỆN TỬ - THANH TOÁN THÀNH CÔNG</div>
      </td></tr>
      <tr><td style="padding:24px">
        <p style="margin:0 0 14px;font-size:15px">Xin chào <b>{{ $customer->name }}</b>, cảm ơn bạn đã đặt vé!</p>
        <div style="text-align:center;padding:8px 0 19px">
          <div style="font-size:21px;font-weight:700;text-transform:uppercase">{{ $showtime->movie->title }}</div>
          <div style="margin-top:8px;color:#1677c8;font-size:14px;font-weight:700">{{ $showtime->room->cinema->name }}</div>
          <div style="margin-top:7px;font-size:13px;color:#596579">{{ $showtime->room->cinema->address ?? '' }}</div>
        </div>
        <div style="border-top:1px dashed #ccd5df;padding-top:18px;text-align:center">
          <div style="font-size:12px;color:#52647a">MÃ VÉ / MÃ ĐẶT CHỖ</div>
          <div style="font-size:25px;font-weight:700;letter-spacing:1px;margin-top:6px">{{ $booking->code }}</div>
          <p style="margin:12px 0 0;font-size:13px;line-height:1.5;color:#52647a">QR của toàn bộ đơn được đính kèm trong email này.<br>Chỉ cần đưa một QR cho nhân viên khi check-in.</p>
        </div>
      </td></tr>
      <tr><td style="padding:0 24px 20px;border-bottom:4px dotted #eef1f4">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-size:14px">
          <tr><td style="padding:12px 0;border-bottom:1px dashed #cbd5e1;color:#52647a">Suất chiếu<br><i style="font-size:11px">Session</i></td><td align="right" style="padding:12px 0;border-bottom:1px dashed #cbd5e1;font-weight:700">{{ $showtime->starts_at->format('d/m/Y H:i') }}</td></tr>
          <tr><td style="padding:12px 0;border-bottom:1px dashed #cbd5e1;color:#52647a">Phòng chiếu<br><i style="font-size:11px">Hall</i></td><td align="right" style="padding:12px 0;border-bottom:1px dashed #cbd5e1;font-weight:700">{{ $showtime->room->name }}</td></tr>
          <tr><td style="padding:12px 0;border-bottom:1px dashed #cbd5e1;color:#52647a">Ghế<br><i style="font-size:11px">Seat</i></td><td align="right" style="padding:12px 0;border-bottom:1px dashed #cbd5e1;font-weight:700">{{ $seatLabels }}</td></tr>
          <tr><td style="padding:12px 0;color:#52647a">Tổng thanh toán<br><i style="font-size:11px">Payment amount</i></td><td align="right" style="padding:12px 0;font-size:16px;font-weight:700;color:#d71920">{{ number_format((int) $booking->total_price, 0, ',', '.') }} VNĐ</td></tr>
        </table>
      </td></tr>
      <tr><td style="padding:20px 24px 24px;background:#fafbfd;font-size:12px;line-height:1.55;color:#4b5563">
        <b>Lưu ý:</b> Vui lòng đến rạp trước giờ chiếu và đưa mã QR/mã đặt chỗ để nhân viên check-in. Vé đã thanh toán không thể hủy hoặc đổi trả theo quy định của rạp.<br><br>
        Cần hỗ trợ? Liên hệ CinemaStar để được giải đáp.
      </td></tr>
    </table>
  </td></tr></table>
</body>
</html>
