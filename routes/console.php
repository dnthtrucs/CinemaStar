<?php

use App\Models\User;
use App\Services\BookingService;
use App\Services\DeploymentCatalogService;
use App\Services\HolidayVoucherService;
use App\Services\MovieScheduleService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('bookings:expire', function (BookingService $service) {
    $count = $service->expirePendingBookings();
    $this->info("Đã giải phóng {$count} đơn hết hạn.");
})->purpose('Expire unpaid bookings and release their seats');

Artisan::command('movies:sync', function (MovieScheduleService $service) {
    $this->info('Đã cập nhật '.$service->sync().' phim.');
})->purpose('Cập nhật trạng thái phim theo ngày khởi chiếu và kết thúc');

Schedule::command('movies:sync')->everyMinute()->withoutOverlapping();

Schedule::command('bookings:expire')->everyMinute()->withoutOverlapping();

Artisan::command('cinema:admin', function () {
    $name = $this->ask('Tên quản trị viên');
    $email = $this->ask('Email quản trị viên');
    $password = $this->secret('Mật khẩu mới (ít nhất 12 ký tự)');
    $confirmation = $this->secret('Nhập lại mật khẩu');
    if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! $name || strlen((string) $password) < 12 || $password !== $confirmation) {
        $this->error('Thông tin không hợp lệ. Tài khoản chưa được tạo.');

        return 1;
    }
    if (User::where('email', $email)->exists()) {
        $this->error('Email đã tồn tại. Lệnh này không thay đổi tài khoản cũ.');

        return 1;
    }
    User::create(['name' => $name, 'email' => $email, 'password' => $password, 'role' => 'admin', 'is_active' => true]);
    $this->info('Đã tạo tài khoản quản trị viên.');

    return 0;
})->purpose('Tạo quản trị viên bằng mật khẩu nhập ẩn, không dùng tài khoản mẫu');

Artisan::command('cinema:publish-catalog', function (DeploymentCatalogService $service) {
    $path = base_path('deploy/catalog.json');
    $key = 'deployment-catalog:'.hash_file('sha256', $path);
    if (Cache::store('database')->has($key)) {
        $this->info('Deployment catalog already applied.');

        return 0;
    }
    $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $this->info(json_encode($service->sync($data)));
    Cache::store('database')->forever($key, true);

    return 0;
})->purpose('Publish the bundled public catalog once without replacing bookings or users');

Artisan::command('vouchers:holidays', function (HolidayVoucherService $service) {
    $this->info('Đã tạo '.$service->sync().' voucher dịp lễ.');
})->purpose('Tạo voucher lễ Tết sắp tới, không thay đổi mã đã có');
Schedule::command('vouchers:holidays')->dailyAt('00:05')->timezone('Asia/Ho_Chi_Minh')->withoutOverlapping();
