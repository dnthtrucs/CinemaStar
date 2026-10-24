<div class="card bg-dark p-2 mb-4 shadow-soft admin-nav">
    <div class="nav nav-pills flex-nowrap overflow-auto">
        @foreach([['admin.dashboard','Tổng quan','bi-speedometer2'],['admin.movies.index','Phim','bi-film'],['admin.banners.index','Banner','bi-images'],['admin.cinemas.index','Rạp','bi-building'],['admin.rooms.index','Phòng','bi-door-open'],['admin.showtimes.index','Suất chiếu','bi-calendar-event'],['admin.bookings.index','Đơn vé','bi-receipt'],['admin.tickets.index','Check-in','bi-qr-code-scan'],['admin.vouchers.index','Voucher','bi-ticket-detailed'],['admin.concessions.index','Bắp nước','bi-cup-straw'],['admin.refunds.index','Hoàn tiền','bi-cash-coin'],['admin.manual-refunds.index','Hoàn thủ công','bi-bank'],['admin.reviews.index','Đánh giá','bi-star'],['admin.users.index','Tài khoản','bi-people'],['admin.reports.revenue','Báo cáo CSV','bi-download'],['admin.reports.pdf','Bản in (HTML)','bi-printer']] as $nav)
            <a class="nav-link text-white-50 text-nowrap {{ request()->routeIs(str_replace('.index','.*',$nav[0])) || request()->routeIs($nav[0]) ? 'active':'' }}" href="{{ route($nav[0]) }}">
                <i class="bi {{ $nav[2] }} me-1"></i>
                {{ $nav[1] }}
            </a>
        @endforeach
    </div>
</div>
