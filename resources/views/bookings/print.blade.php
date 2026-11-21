<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vé phim {{ $booking->code }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { size: auto; margin: 0; }
        body { margin: 0; background: #ececec; color: #000; font: 12px/1.45 'Courier New', monospace; }
        .toolbar { max-width: 420px; margin: 20px auto; padding: 0 16px; font-family: Arial, sans-serif; text-align: center; }
        .toolbar p { font-size: 13px; line-height: 1.5; }
        .toolbar button { border: 0; border-radius: 6px; padding: 10px 24px; background: #151515; color: white; cursor: pointer; font-size: 14px; }
        .ticket { width: 80mm; margin: 20px auto; padding: 9mm 4mm 6mm; background: white; box-shadow: 0 2px 14px #0001; overflow-wrap: anywhere; }
        .brand { text-align: center; font: 900 26px/1 Arial, sans-serif; letter-spacing: -1px; }
        .tagline { margin-top: 4px; text-align: center; font: bold 8px Arial, sans-serif; letter-spacing: 2px; }
        .cinema { margin: 16px 0 20px; text-align: center; font-weight: bold; font-size: 11px; }
        .pass { text-align: center; font-weight: bold; font-size: 29px; letter-spacing: 1px; margin-bottom: 12px; }
        .movie { text-align: center; text-transform: uppercase; font-size: 19px; line-height: 1.3; margin: 0 0 4px; }
        .rating { text-align: center; font-size: 19px; font-weight: bold; margin-bottom: 20px; }
        .schedule { display: grid; grid-template-columns: 1fr 1.3fr; gap: 10px; }
        .label { font-size: 10px; font-weight: bold; }
        .time, .date { margin-top: 4px; font-size: 25px; line-height: 1.2; white-space: nowrap; }
        .date { font-size: 21px; }
        .hall { margin-top: 18px; }
        .hall-value { font-size: 21px; font-weight: bold; margin: 4px 0; line-height: 1.3; }
        .format { font-size: 10px; margin: 7px 0 18px; }
        .line { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; margin: 6px 0; }
        .line > :last-child { text-align: right; }
        .amount { flex-shrink: 0; white-space: nowrap; }
        .total { border-top: 1px dashed black; padding-top: 10px; margin-top: 10px; font-weight: bold; }
        .total .amount { font-size: 21px; }
        .concessions { margin: 14px 0; border-top: 1px dashed black; padding-top: 10px; font-size: 10px; }
        .details { margin: 18px 0; font-size: 10px; }
        .details p { margin: 5px 0; }
        .qr-row { display: block; align-items: center; gap: 10px; border-top: 1px dashed black; padding-top: 14px; }
        .qr-row img { width: 100%; height: auto; flex-shrink: 0; image-rendering: pixelated; }
        .qr-note { font-size: 10px; }
        .thanks { text-align: center; font-size: 10px; margin: 17px 0 0; }
        @media print {
            html, body { width: 80mm; background: white; }
            .toolbar { display: none; }
            .ticket { margin: 0; box-shadow: none; }
        }
        @media screen and (max-width: 320px) { .ticket { margin-left: 0; margin-right: 0; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">In vé</button>
        <p>Chọn giấy 80 mm, tỷ lệ 100%, lề bằng 0 và tắt đầu trang/chân trang trong hộp thoại in.</p>
    </div>
    <main class="ticket" aria-label="Vé xem phim">
        <header>
            <div class="brand">CINEMASTAR</div>
            <div class="tagline">TRẢI NGHIỆM ĐIỆN ẢNH</div>
            <div class="cinema">{{ $booking->showtime->room->cinema->name }}</div>
        </header>
        <div class="pass">VÉ XEM PHIM</div>
        <h1 class="movie">{{ $booking->showtime->movie->title }}</h1>
        <div class="rating">({{ $booking->showtime->movie->age_rating_label }})</div>
        <div class="schedule">
            <div><div class="label">SUẤT/TIME:</div><div class="time">{{ $booking->showtime->starts_at->format('H:i') }}</div></div>
            <div><div class="label">NGÀY/DATE:</div><div class="date">{{ $booking->showtime->starts_at->format('d/m/Y') }}</div></div>
        </div>
        <div class="hall">
            <div class="label">PHÒNG/HALL · GHẾ/SEAT:</div>
            <div class="hall-value">{{ $booking->showtime->room->name }} / {{ $booking->tickets->pluck('seat.label')->filter()->join(', ') }}</div>
        </div>
        <div class="format">{{ $booking->showtime->format }} · {{ $booking->showtime->language }}@if($booking->showtime->subtitle) · Phụ đề {{ $booking->showtime->subtitle }}@endif</div>
        <div class="line"><span>Tiền vé/Price:</span><span class="amount">{{ number_format(max(0, (int) $booking->subtotal - (int) $booking->concessions_total), 0, ',', '.') }} VNĐ</span></div>
        @if((int) $booking->concessions_total > 0)
            <div class="line"><span>Bắp nước:</span><span class="amount">{{ number_format((int) $booking->concessions_total, 0, ',', '.') }} VNĐ</span></div>
        @endif
        @if((int) $booking->discount > 0)
            <div class="line"><span>Giảm giá:</span><span class="amount">-{{ number_format((int) $booking->discount, 0, ',', '.') }} VNĐ</span></div>
        @endif
        @if((int) $booking->points_used > 0)
            <div class="line"><span>Đổi điểm:</span><span class="amount">-{{ number_format((int) $booking->points_used * 1000, 0, ',', '.') }} VNĐ</span></div>
        @endif
        <div class="line total"><span>Tổng/Total:</span><span class="amount">{{ number_format((int) $booking->total_price, 0, ',', '.') }} VNĐ</span></div>
        @if($booking->concessions->isNotEmpty())
            <section class="concessions" aria-label="Bắp nước trong đơn">
                <strong>CHI TIẾT BẮP NƯỚC</strong>
                @foreach($booking->concessions as $item)
                    <div class="line"><span>{{ $item->pivot->name }}<br>{{ $item->pivot->quantity }} × {{ number_format($item->pivot->unit_price, 0, ',', '.') }} VNĐ</span><span class="amount">{{ number_format($item->pivot->unit_price * $item->pivot->quantity, 0, ',', '.') }} VNĐ</span></div>
                @endforeach
            </section>
        @endif
        <div class="details">
            <p><strong>Mã đơn/Booking:</strong> {{ $booking->code }}</p>
            <p><strong>Số ghế/Seats:</strong> {{ $booking->tickets->count() }}</p>
            <p><strong>Thanh toán:</strong> Đã thanh toán</p>
            <p><strong>Ngày đặt:</strong> {{ $booking->created_at->format('d/m/Y H:i') }}</p>
            <p><strong>Ngày in:</strong> {{ now()->format('d/m/Y H:i') }}</p>
        </div>
        <div class="qr-row">
            <img src="{{ $barcodeDataUri }}" alt="Mã vạch đơn đặt {{ $booking->code }}">
            <div class="qr-note"><strong>QUÉT MÃ VẠCH KIỂM TRA VÉ</strong><p>Một mã vạch cho toàn bộ {{ $booking->tickets->count() }} ghế trong đơn. Xuất trình vé khi vào phòng chiếu.</p></div>
        </div>
        <p class="thanks">Vui lòng đến trước giờ chiếu 15 phút.<br>Cảm ơn bạn đã chọn CinemaStar!</p>
    </main>
    @unless(request()->boolean('preview'))
        <script>window.addEventListener('load', function () { window.print(); });</script>
    @endunless
</body>
</html>
