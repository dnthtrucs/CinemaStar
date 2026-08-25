<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Vé phim {{ $booking->code }}</title>
    <style>body{font-family:Arial,sans-serif;margin:0;color:#151515}.ticket{width:760px;margin:24px auto;border:2px dashed #333;padding:28px;box-sizing:border-box}.top{display:flex;justify-content:space-between;gap:24px}.brand{color:#c8102e;font-size:28px;font-weight:800}.movie{font-size:24px;font-weight:800;margin:20px 0}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.label{color:#666;font-size:12px;text-transform:uppercase}.value{font-size:16px;font-weight:700;margin-top:4px}.qr{text-align:center;min-width:230px}.qr img{width:210px;height:210px}.note{margin-top:20px;font-size:13px;color:#555}@media print{.ticket{margin:0;border-width:1px}}</style>
</head>
<body onload="window.print()">
    <section class="ticket">
        <div class="top">
            <div>
                <div class="brand">CINEMASTAR</div>
                <div class="movie">{{ $booking->showtime->movie->title }}</div>
                <div class="grid">
                    <div><div class="label">Mã đơn</div><div class="value">{{ $booking->code }}</div></div>
                    <div><div class="label">Số ghế</div><div class="value">{{ $booking->tickets->count() }}</div></div>
                    <div><div class="label">Rạp / phòng</div><div class="value">{{ $booking->showtime->room->cinema->name }} - {{ $booking->showtime->room->name }}</div></div>
                    <div><div class="label">Suất chiếu</div><div class="value">{{ $booking->showtime->starts_at->format('H:i, d/m/Y') }}</div></div>
                    <div><div class="label">Tất cả ghế</div><div class="value">{{ $booking->tickets->pluck('seat.label')->filter()->join(', ') }}</div></div>
                    <div><div class="label">Tổng tiền</div><div class="value">{{ number_format((int) $booking->total_price, 0, ',', '.') }}₫</div></div>
                </div>
            </div>
            <div class="qr"><img src="{{ $qrDataUri }}" alt="QR đơn đặt {{ $booking->code }}"><div class="label">Một QR cho toàn bộ đơn</div></div>
        </div>
        <p class="note">Vé này áp dụng cho tất cả ghế ghi trên vé. Xuất trình một mã QR tại quầy/rạp.</p>
    </section>
</body>
</html>
