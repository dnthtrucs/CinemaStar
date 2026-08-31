@extends('layouts.app')

@section('title', 'Hỗ trợ khách hàng')

@section('content')
<div class="container py-5">
    @php($supportEmail = config('mail.from.address'))
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
        <div>
            <div class="text-brand fw-semibold small text-uppercase">CinemaStar Care</div>
            <h1 class="section-title mb-1">Hỗ trợ khách hàng</h1>
            <p class="text-muted mb-0">Tìm nhanh câu trả lời về đặt vé, thanh toán và check-in.</p>
        </div>
        <a class="btn btn-primary" href="mailto:{{ $supportEmail }}?subject=Ho%20tro%20CinemaStar"><i class="bi bi-envelope me-1"></i>Liên hệ CSKH</a>
    </div>

    <ul class="nav nav-pills support-tabs flex-nowrap overflow-auto mb-4" id="supportTabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#faq" type="button">Câu hỏi thường gặp</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#payment" type="button">Thanh toán</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#ticket" type="button">Vé & check-in</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#contact" type="button">Liên hệ CSKH</button></li>
    </ul>

    <div class="tab-content">
        <section class="tab-pane fade show active" id="faq">
            <div class="card shadow-soft p-4 mb-4">
                <label for="supportSearch" class="form-label fw-semibold"><i class="bi bi-search me-1"></i>Tìm kiếm câu hỏi</label>
                <input id="supportSearch" class="form-control form-control-lg" placeholder="Ví dụ: thanh toán, QR, hoàn tiền, ghế đôi...">
            </div>

            <div class="accordion shadow-soft rounded-4 overflow-hidden" id="supportFaq">
                @foreach([
                    ['Tôi chọn ghế nhưng chưa đăng nhập thì có bị mất lựa chọn không?', 'Không. Khi bạn bấm Đăng nhập để đặt vé, CinemaStar lưu ghế và mã giảm giá đã chọn trên trình duyệt. Đăng nhập hoặc đăng ký xong sẽ quay lại đúng suất chiếu để tiếp tục.'],
                    ['Ghế VIP và ghế đôi được tính giá như thế nào?', 'Ghế VIP hàng E, F, G phụ thu 30.000₫ mỗi ghế. Ghế đôi hàng H được chọn theo cặp; giá một cặp là 2 × (giá ghế cơ bản + 30.000₫) + 30.000₫.'],
                    ['Tôi thanh toán SePay xong bao lâu thì nhận được vé?', 'Sau khi SePay gửi giao dịch hợp lệ về hệ thống, đơn tự chuyển sang Đã thanh toán, QR được phát hành và email xác nhận được gửi.'],
                    ['Một đơn nhiều ghế dùng QR nào để vào rạp?', 'Mỗi đơn chỉ có một QR. Nhân viên quét QR này một lần để check-in toàn bộ ghế trong đơn.'],
                    ['Tôi có thể hủy hoặc hoàn tiền vé không?', 'Bạn có thể gửi yêu cầu hoàn tiền từ chi tiết đơn nếu vé còn thỏa điều kiện. Quản trị viên sẽ duyệt hoặc từ chối theo chính sách rạp.'],
                ] as $index => $item)
                    <div class="accordion-item support-item" data-search="{{ strtolower($item[0].' '.$item[1]) }}">
                        <h2 class="accordion-header"><button class="accordion-button {{ $index ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#supportAnswer{{ $index }}">{{ $item[0] }}</button></h2>
                        <div id="supportAnswer{{ $index }}" class="accordion-collapse collapse {{ $index ? '' : 'show' }}" data-bs-parent="#supportFaq"><div class="accordion-body text-muted">{{ $item[1] }}</div></div>
                    </div>
                @endforeach
            </div>
            <p id="supportEmpty" class="text-muted text-center py-4 d-none">Không tìm thấy câu hỏi phù hợp. Hãy liên hệ CSKH để được hỗ trợ.</p>
        </section>

        <section class="tab-pane fade" id="payment">
            <div class="row g-4">
                <div class="col-md-6"><div class="card shadow-soft p-4 h-100"><i class="bi bi-qr-code-scan fs-2 text-brand"></i><h4 class="fw-bold mt-3">Thanh toán QR SePay</h4><p class="text-muted mb-0">Quét QR bằng ứng dụng ngân hàng, chuyển đúng số tiền và giữ nguyên nội dung chuyển khoản. Vé chỉ được xác nhận khi hệ thống nhận webhook giao dịch hợp lệ.</p></div></div>
                <div class="col-md-6"><div class="card shadow-soft p-4 h-100"><i class="bi bi-wallet2 fs-2 text-brand"></i><h4 class="fw-bold mt-3">MoMo và VNPAY</h4><p class="text-muted mb-0">Khi được cấu hình tài khoản merchant, hệ thống chuyển bạn đến trang hoặc ứng dụng chính thức của cổng thanh toán để xác nhận giao dịch.</p></div></div>
            </div>
        </section>

        <section class="tab-pane fade" id="ticket">
            <div class="card shadow-soft p-4 p-md-5">
                <h3 class="fw-bold">Hướng dẫn vào rạp</h3>
                <ol class="text-muted mb-0 lh-lg">
                    <li>Mở mục <strong>Vé của tôi</strong> sau khi đơn hiển thị Đã thanh toán.</li>
                    <li>Xuất trình QR duy nhất của đơn cho nhân viên.</li>
                    <li>Nhân viên quét QR để check-in toàn bộ ghế trong đơn.</li>
                    <li>QR chỉ dùng một lần; hãy đến rạp trước giờ chiếu.</li>
                </ol>
            </div>
        </section>

        <section class="tab-pane fade" id="contact">
            <div class="row g-4">
                <div class="col-md-6"><div class="card shadow-soft p-4 h-100"><i class="bi bi-envelope fs-2 text-brand"></i><h4 class="fw-bold mt-3">Email hỗ trợ</h4><p class="text-muted">Gửi mã đơn BK và mô tả vấn đề để được hỗ trợ nhanh hơn.</p><a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a></div></div>
                <div class="col-md-6"><div class="card shadow-soft p-4 h-100"><i class="bi bi-receipt fs-2 text-brand"></i><h4 class="fw-bold mt-3">Yêu cầu hoàn tiền</h4><p class="text-muted">Đăng nhập, mở đơn vé và chọn yêu cầu hoàn tiền nếu đơn đủ điều kiện.</p>@auth<a class="btn btn-outline-danger" href="{{ route('bookings.index') }}">Mở Vé của tôi</a>@else<a class="btn btn-outline-danger" href="{{ route('login') }}">Đăng nhập</a>@endauth</div></div>
            </div>
        </section>
    </div>
</div>
@endsection

@push('styles')
<style>
    .support-tabs { gap:.5rem; }
    .support-tabs .nav-link { color:#444; background:#ece8d8; white-space:nowrap; padding:.75rem 1.1rem; }
    .support-tabs .nav-link.active { background:#242021; color:#fff; }
    .support-item { border-color:#ececec; }
</style>
@endpush

@push('scripts')
<script>
    const supportSearch = document.getElementById('supportSearch');
    if (supportSearch) {
        supportSearch.addEventListener('input', () => {
            const keyword = supportSearch.value.trim().toLocaleLowerCase('vi');
            let visible = 0;
            document.querySelectorAll('.support-item').forEach(item => {
                const matches = item.dataset.search.includes(keyword);
                item.classList.toggle('d-none', !matches);
                if (matches) visible++;
            });
            document.getElementById('supportEmpty').classList.toggle('d-none', visible > 0);
        });
    }
</script>
@endpush
