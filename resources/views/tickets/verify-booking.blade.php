<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kiểm tra vé {{ $booking->code }}</title>
    <style>body{font-family:Arial,sans-serif;background:#f4f6f8;margin:0;padding:24px}.card{max-width:560px;margin:auto;background:#fff;border-radius:14px;padding:28px;box-shadow:0 4px 18px #0001}h1{margin-top:0;color:#16803a}.row{padding:11px 0;border-bottom:1px solid #eee}.label{color:#667085;font-size:13px}.value{font-weight:700;margin-top:3px}.status{font-size:18px;font-weight:700;padding:10px 14px;border-radius:10px}.status-primary{color:#16803a;background:#e8f6ed}.status-success{color:#1d4ed8;background:#e8effd}.status-warning{color:#b45309;background:#fdf3e3}.status-secondary{color:#b42318;background:#fdecea}</style>
</head>
<body>
    <main class="card">
        <p class="status status-{{ $booking->ticket_status_badge }}">
            {{ $booking->ticket_status === 'valid' ? '✓ Vé hợp lệ' : '✕ '.$booking->ticket_status_label }}
        </p>
        <h1>{{ $booking->showtime->movie->title }}</h1>
        <div class="row"><div class="label">Mã đơn</div><div class="value">{{ $booking->code }}</div></div>
        <div class="row"><div class="label">Rạp / phòng</div><div class="value">{{ $booking->showtime->room->cinema->name }} - {{ $booking->showtime->room->name }}</div></div>
        <div class="row"><div class="label">Suất chiếu</div><div class="value">{{ $booking->showtime->starts_at->format('H:i, d/m/Y') }}</div></div>
        <div class="row"><div class="label">Tất cả ghế trong đơn</div><div class="value">{{ $booking->tickets->pluck('seat.label')->filter()->join(', ') }}</div></div>
        <div class="row"><div class="label">Số ghế</div><div class="value">{{ $booking->tickets->count() }}</div></div>
    </main>
</body>
</html>
