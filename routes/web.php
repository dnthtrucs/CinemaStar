<?php

use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\CinemaController as AdminCinemaController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\MovieController as AdminMovieController;
use App\Http\Controllers\Admin\RoomController as AdminRoomController;
use App\Http\Controllers\Admin\ShowtimeController as AdminShowtimeController;
use App\Http\Controllers\Admin\TicketController as AdminTicketController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\VoucherController as AdminVoucherController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\BookingTicketController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CinemaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShowtimeController;
use App\Http\Controllers\TicketVerificationController;
use App\Http\Controllers\MovieReviewController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\Admin\RefundController as AdminRefundController;
use App\Http\Controllers\Admin\ReviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/movies', [MovieController::class, 'index'])->name('movies.index');
Route::get('/movies/{movie}', [MovieController::class, 'show'])->name('movies.show');
Route::get('/cinemas', [CinemaController::class, 'index'])->name('cinemas.index');
Route::get('/cinemas/{cinema}', [CinemaController::class, 'show'])->name('cinemas.show');
Route::get('/showtimes/{showtime}', [ShowtimeController::class, 'show'])->name('showtimes.show');
Route::get('/tickets/verify/{qrToken}', [TicketVerificationController::class, 'show'])
    ->where('qrToken', '[A-Fa-f0-9]{64}')
    ->name('tickets.verify');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::post('/showtimes/{showtime}/bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
    Route::delete('/bookings/{booking}', [BookingController::class, 'cancel'])->name('bookings.cancel');
    Route::get('/bookings/{booking}/refund', [RefundController::class, 'create'])->name('refunds.create');
    Route::post('/bookings/{booking}/refund', [RefundController::class, 'store'])->middleware('throttle:6,1')->name('refunds.store');
    Route::post('/movies/{movie}/reviews', [MovieReviewController::class, 'store'])->middleware('throttle:10,1')->name('movies.reviews.store');

    Route::get('/bookings/{booking}/payment', [PaymentController::class, 'show'])->name('payments.show');
    Route::post('/bookings/{booking}/payment', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('/payments/{payment}/simulate', [PaymentController::class, 'simulate'])->name('payments.simulate');
    Route::post('/payments/{payment}/simulate', [PaymentController::class, 'completeSimulation'])->name('payments.simulate.complete');
    Route::get('/payments/{payment}/demo', [PaymentController::class, 'demo'])->name('payments.demo');
    Route::post('/payments/{payment}/demo', [PaymentController::class, 'completeDemo'])->name('payments.demo.complete');
    Route::get('/verify-ticket/{booking}', [BookingTicketController::class, 'verify'])->name('bookings.verify');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/payments/vnpay/return', [PaymentController::class, 'vnpayReturn'])->name('payments.vnpay.return');
Route::get('/payments/vnpay/ipn', [PaymentController::class, 'vnpayIpn'])->name('payments.vnpay.ipn');
Route::get('/payments/momo/return', [PaymentController::class, 'momoReturn'])->name('payments.momo.return');
Route::post('/payments/momo/ipn', [PaymentController::class, 'momoIpn'])->name('payments.momo.ipn');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::resource('movies', AdminMovieController::class)->except('show');
    Route::resource('banners', AdminBannerController::class)->except('show');
    Route::resource('cinemas', AdminCinemaController::class)->except('show');
    Route::resource('rooms', AdminRoomController::class)->except('show');
    Route::get('/showtimes/bulk/create', [AdminShowtimeController::class, 'bulkCreate'])->name('showtimes.bulk.create');
    Route::post('/showtimes/bulk', [AdminShowtimeController::class, 'bulkStore'])->name('showtimes.bulk.store');
    Route::resource('showtimes', AdminShowtimeController::class)->except('show');
    Route::resource('bookings', AdminBookingController::class)->only(['index', 'show']);
    Route::resource('users', AdminUserController::class)->only(['index', 'update']);
    Route::get('/vouchers', [AdminVoucherController::class, 'index'])->name('vouchers.index');
    Route::post('/vouchers', [AdminVoucherController::class, 'store'])->name('vouchers.store');
    Route::patch('/vouchers/{voucher}', [AdminVoucherController::class, 'update'])->name('vouchers.update');
    Route::get('/reports/revenue.csv', [AdminReportController::class, 'revenueCsv'])->name('reports.revenue');
    Route::get('/reports/revenue.xls', [AdminReportController::class, 'excel'])->name('reports.excel');
    Route::get('/reports/revenue.pdf', [AdminReportController::class, 'pdf'])->name('reports.pdf');
    Route::get('/refunds', [AdminRefundController::class, 'index'])->name('refunds.index');
    Route::patch('/refunds/{refund}', [AdminRefundController::class, 'update'])->name('refunds.update');
    Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');
    Route::patch('/reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');
    Route::get('/tickets/check-in', [AdminTicketController::class, 'index'])->name('tickets.index');
    Route::patch('/tickets/{booking}/check-in', [AdminTicketController::class, 'update'])->name('tickets.update');
});

Route::prefix('staff')->name('staff.')->middleware(['auth', 'staff'])->group(function () {
    Route::get('/tickets/check-in', [AdminTicketController::class, 'index'])->name('tickets.index');
    Route::patch('/tickets/{booking}/check-in', [AdminTicketController::class, 'update'])->name('tickets.update');
});

require __DIR__.'/auth.php';
