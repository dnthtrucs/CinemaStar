<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\BookingService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('bookings:expire', function (BookingService $service) {
    $count = $service->expirePendingBookings();
    $this->info("Đã giải phóng {$count} đơn hết hạn.");
})->purpose('Expire unpaid bookings and release their seats');

Schedule::command('bookings:expire')->everyMinute()->withoutOverlapping();
