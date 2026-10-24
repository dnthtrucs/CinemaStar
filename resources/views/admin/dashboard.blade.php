@extends('layouts.app')
@section('title', 'Quản trị')
@section('content')
    <div class="container py-4">
        @include('admin.partials.nav')
        @if($latePayments->isNotEmpty())
            <section class="card border-warning p-4 mb-4" aria-labelledby="manual-refunds-heading">
                <h2 class="h5" id="manual-refunds-heading">Cần xử lý hoàn tiền</h2>
                <p>Tiền đã đến nhưng đơn không thể xác nhận. Kiểm tra giao dịch và liên hệ khách để hoàn tiền thủ công.</p>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col">Giao dịch</th>
                                <th scope="col">Đơn</th>
                                <th scope="col">Khách</th>
                                <th scope="col">Số tiền</th>
                                <th scope="col">Mã ngân hàng</th>
                                <th scope="col">Lý do</th>
                                <th scope="col">Xử lý</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($latePayments as $payment)
                                <tr>
                                    <td>{{ $payment->request_id }}</td>
                                    <td><a href="{{ route('admin.bookings.show', $payment->booking) }}">{{ $payment->booking->code }}</a></td>
                                    <td>{{ $payment->booking->user->name }}</td>
                                    <td>{{ number_format($payment->amount, 0, ',', '.') }}₫</td>
                                    <td>{{ $payment->transaction_id }}</td>
                                    <td>{{ \App\Models\Payment::MANUAL_REFUND_REASONS[$payment->response_code] }}</td>
                                    <td>@include('admin.manual-refunds.resolve-form')</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $latePayments->links() }}
            </section>
        @endif
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-2 mb-4">
            <div>
                <div class="text-brand fw-semibold">QUẢN TRỊ HỆ THỐNG</div>
                <h1 class="section-title mb-0">Tổng quan kinh doanh</h1>
            </div>
            <div class="text-muted small">Cập nhật {{ now()->timezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y') }} · Giờ Việt Nam</div>
        </div>
        <div class="row g-3 mb-4">
            @foreach([['Doanh thu',number_format($stats['revenue'],0,',','.').'₫','bi-cash-stack','success'],['Doanh thu hôm nay',number_format($stats['today_revenue'],0,',','.').'₫','bi-graph-up-arrow','primary'],['Đơn đặt vé',$stats['bookings'],'bi-ticket-perforated','warning'],['Khách hàng',$stats['customers'],'bi-people','info']] as $item)
                <div class="col-md-6 col-xl-3">
                    <div class="card shadow-soft p-4 h-100">
                        <div class="d-flex justify-content-between">
                            <div><div class="text-muted small">{{ $item[0] }}</div><div class="fs-3 fw-bold">{{ $item[1] }}</div></div>
                            <div class="fs-2 text-{{ $item[3] }}"><i class="bi {{ $item[2] }}" aria-hidden="true"></i></div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <section class="mb-4" aria-labelledby="reports-heading">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-3">
                <div>
                    <h2 id="reports-heading" class="h4 fw-bold mb-1">Doanh thu 30 ngày</h2>
                    <p class="text-muted small mb-0">{{ \Carbon\CarbonImmutable::parse($reports['period']['from'], 'Asia/Ho_Chi_Minh')->format('d/m/Y') }} — {{ \Carbon\CarbonImmutable::parse($reports['period']['to'], 'Asia/Ho_Chi_Minh')->format('d/m/Y') }} · Giờ Việt Nam, tính đến hiện tại.</p>
                </div>
                <a href="{{ route('admin.reports.revenue', ['from' => $reports['period']['from'], 'to' => $reports['period']['to']]) }}" class="btn btn-outline-dark btn-sm align-self-start align-self-md-auto"><i class="bi bi-download me-1" aria-hidden="true"></i>Tải báo cáo CSV</a>
            </div>
            <p class="small text-muted">Chỉ tính giao dịch thành công của đơn đã thanh toán; không tính đơn đã hoàn tiền. Mở bảng số liệu dưới mỗi biểu đồ để xem số chính xác, kể cả khi trình duyệt không bật JavaScript.</p>
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card shadow-soft p-4 h-100">
                        <h3 class="h5 fw-bold">Doanh thu theo ngày</h3>
                        @if($reports['revenue_split']['total'] === 0)<p class="small text-muted">Chưa có giao dịch thành công trong kỳ.</p>@endif
                        <div style="height:300px"><canvas id="daily-revenue-chart" role="img" aria-label="Doanh thu theo ngày trong 30 ngày. Bảng số liệu nằm bên dưới."></canvas></div>
                        <details class="mt-3">
                            <summary class="fw-semibold">Xem bảng doanh thu theo ngày</summary>
                            <div class="table-responsive mt-2">
                                <table class="table table-sm align-middle mb-0">
                                    <caption class="visually-hidden">Doanh thu theo ngày, đơn vị đồng Việt Nam</caption>
                                    <thead><tr><th scope="col">Ngày</th><th scope="col" class="text-end">Doanh thu</th></tr></thead>
                                    <tbody>
                                        @foreach($reports['daily'] as $day)
                                            <tr><th scope="row" class="fw-normal">{{ \Carbon\CarbonImmutable::parse($day['date'], 'Asia/Ho_Chi_Minh')->format('d/m/Y') }}</th><td class="text-end">{{ number_format($day['revenue'], 0, ',', '.') }}₫</td></tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot><tr><th scope="row">Tổng cộng</th><td class="text-end fw-bold">{{ number_format($reports['revenue_split']['total'], 0, ',', '.') }}₫</td></tr></tfoot>
                                </table>
                            </div>
                        </details>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card shadow-soft p-4 h-100">
                        <h3 class="h5 fw-bold">Top 10 phim theo doanh thu</h3>
                        @if(empty($reports['top_movies']))<p class="small text-muted">Chưa có phim phát sinh doanh thu trong kỳ.</p>@endif
                        <div style="height:300px"><canvas id="top-movies-chart" role="img" aria-label="Top 10 phim theo doanh thu. Bảng số liệu nằm bên dưới."></canvas></div>
                        <details class="mt-3">
                            <summary class="fw-semibold">Xem bảng doanh thu theo phim</summary>
                            <div class="table-responsive mt-2">
                                <table class="table table-sm align-middle mb-0">
                                    <caption class="visually-hidden">Top 10 phim theo doanh thu, đơn vị đồng Việt Nam</caption>
                                    <thead><tr><th scope="col">Phim</th><th scope="col" class="text-end">Doanh thu</th></tr></thead>
                                    <tbody>
                                        @forelse($reports['top_movies'] as $movie)
                                            <tr><th scope="row" class="fw-normal">{{ $movie['title'] }}</th><td class="text-end">{{ number_format($movie['revenue'], 0, ',', '.') }}₫</td></tr>
                                        @empty
                                            <tr><td colspan="2" class="text-muted">Chưa có dữ liệu.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </details>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card shadow-soft p-4 h-100">
                        <h3 class="h5 fw-bold">Tỷ lệ lấp đầy ghế</h3>
                        <p class="small text-muted">Vé đã thanh toán / tổng ghế của các suất đã kết thúc trong kỳ. Không tính suất đã hủy; vé được thanh toán trước kỳ vẫn tính vào ghế đã bán.</p>
                        @if(empty($reports['occupancy']))<p class="small text-muted">Chưa có suất chiếu đã kết thúc trong kỳ.</p>@endif
                        <div style="height:300px"><canvas id="occupancy-chart" role="img" aria-label="Tỷ lệ lấp đầy ghế theo phim. Bảng số liệu nằm bên dưới."></canvas></div>
                        <details class="mt-3">
                            <summary class="fw-semibold">Xem bảng lấp đầy ghế</summary>
                            <div class="table-responsive mt-2">
                                <table class="table table-sm align-middle mb-0">
                                    <caption class="visually-hidden">Ghế đã bán, tổng ghế và tỷ lệ lấp đầy theo phim</caption>
                                    <thead><tr><th scope="col">Phim</th><th scope="col" class="text-end">Đã bán</th><th scope="col" class="text-end">Tổng ghế</th><th scope="col" class="text-end">Tỷ lệ</th></tr></thead>
                                    <tbody>
                                        @forelse($reports['occupancy'] as $movie)
                                            <tr><th scope="row" class="fw-normal">{{ $movie['title'] }}</th><td class="text-end">{{ number_format($movie['sold'], 0, ',', '.') }}</td><td class="text-end">{{ number_format($movie['capacity'], 0, ',', '.') }}</td><td class="text-end">{{ number_format($movie['percentage'], 2, ',', '.') }}%</td></tr>
                                        @empty
                                            <tr><td colspan="4" class="text-muted">Chưa có dữ liệu.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </details>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card shadow-soft p-4 h-100">
                        <h3 class="h5 fw-bold">Vé và combo bắp nước</h3>
                        <p class="small text-muted">Tiền thực thu sau giảm giá và đổi điểm, phân bổ theo giá gốc trong đơn. Phần combo làm tròn đến đồng; phần lẻ còn lại tính vào vé.</p>
                        @if($reports['revenue_split']['total'] === 0)<p class="small text-muted">Chưa có giao dịch thành công trong kỳ.</p>@endif
                        <div style="height:300px"><canvas id="revenue-split-chart" role="img" aria-label="Doanh thu vé so với combo bắp nước. Bảng số liệu nằm bên dưới."></canvas></div>
                        <details class="mt-3">
                            <summary class="fw-semibold">Xem bảng doanh thu vé và combo</summary>
                            <div class="table-responsive mt-2">
                                <table class="table table-sm align-middle mb-0">
                                    <caption class="visually-hidden">Doanh thu vé và combo sau ưu đãi, đơn vị đồng Việt Nam</caption>
                                    <thead><tr><th scope="col">Khoản thu</th><th scope="col" class="text-end">Doanh thu</th></tr></thead>
                                    <tbody>
                                        <tr><th scope="row" class="fw-normal">Vé</th><td class="text-end">{{ number_format($reports['revenue_split']['tickets'], 0, ',', '.') }}₫</td></tr>
                                        <tr><th scope="row" class="fw-normal">Combo bắp nước</th><td class="text-end">{{ number_format($reports['revenue_split']['concessions'], 0, ',', '.') }}₫</td></tr>
                                    </tbody>
                                    <tfoot><tr><th scope="row">Tổng cộng</th><td class="text-end fw-bold">{{ number_format($reports['revenue_split']['total'], 0, ',', '.') }}₫</td></tr></tfoot>
                                </table>
                            </div>
                        </details>
                    </div>
                </div>
            </div>
        </section>

        <section class="card shadow-soft p-4" aria-labelledby="recent-bookings-heading">
            <h2 class="h5 fw-bold" id="recent-bookings-heading">Đơn gần đây</h2>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th scope="col">Mã</th><th scope="col">Khách hàng</th><th scope="col">Phim</th><th scope="col">Trạng thái</th><th scope="col"><span class="visually-hidden">Xem đơn</span></th></tr></thead>
                    <tbody>
                        @forelse($recentBookings as $booking)
                            <tr>
                                <td class="fw-semibold">{{ $booking->code }}</td>
                                <td>{{ $booking->user->name }}</td>
                                <td>{{ $booking->showtime->movie->title }}</td>
                                <td><span class="badge text-bg-{{ $booking->status === 'confirmed' ? 'success' : ($booking->status === 'pending' ? 'warning' : 'secondary') }}">{{ match($booking->status) {'pending' => 'Chờ thanh toán', 'confirmed' => 'Đã thanh toán', 'cancelled' => 'Đã hủy', 'expired' => 'Đã hết hạn', default => $booking->status} }}</span></td>
                                <td><a href="{{ route('admin.bookings.show', $booking) }}" aria-label="Xem đơn {{ $booking->code }}"><i class="bi bi-chevron-right" aria-hidden="true"></i></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">Chưa có đơn đặt vé.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
@push('scripts')
    <script type="application/json" id="admin-dashboard-data">@json($reports)</script>
    @vite('resources/js/admin-dashboard.js')
@endpush
