<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kiểm tra vé {{ $booking->code }}</title>
    <style>body{font-family:Arial,sans-serif;background:#f4f6f8;margin:0;padding:24px}.card{max-width:560px;margin:auto;background:#fff;border-radius:14px;padding:28px;box-shadow:0 4px 18px #0001}h1{margin-top:0;color:#16803a}.row{padding:11px 0;border-bottom:1px solid #eee}.label{color:#667085;font-size:13px}.value{font-weight:700;margin-top:3px}.ok{color:#16803a;font-size:18px;font-weight:700}</style>
</head>
<body>
    <main class="card">
        <p class="ok">✓ Vé hợp lệ — một QR cho toàn bộ đơn</p>
        <h1>{{ $booking->showtime->movie->title }}</h1>
        <div class="row"><div class="label">Mã đơn</div><div class="value">{{ $booking->code }}</div></div>
        <div class="row"><div class="label">Rạp / phòng</div><div class="value">{{ $booking->showtime->room->cinema->name }} - {{ $booking->showtime->room->name }}</div></div>
        <div class="row"><div class="label">Suất chiếu</div><div class="value">{{ $booking->showtime->starts_at->format('H:i, d/m/Y') }}</div></div>
        <div class="row"><div class="label">Tất cả ghế trong đơn</div><div class="value">{{ $booking->tickets->pluck('seat.label')->filter()->join(', ') }}</div></div>
        <div class="row"><div class="label">Số ghế</div><div class="value">{{ $booking->tickets->count() }}</div></div>
    </main>
</body>
</html>
