<?php

use App\Models\Showtime;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if ($app['db']->connection()->getDatabaseName() !== 'cinema_test') {
    throw new RuntimeException('Test database only');
}
[$script,$userId,$showtimeId,$seatId,$voucher,$ready,$other] = $argv;
touch($ready);
$deadline = microtime(true) + 10;
do {
    clearstatcache(true, $other);
    if (is_file($other)) {
        break;
    } usleep(10000);
} while (microtime(true) < $deadline);
if (! is_file($other)) {
    throw new RuntimeException('Barrier timeout');
}
try {
    $b = app(BookingService::class)->create(User::findOrFail($userId), Showtime::findOrFail($showtimeId), [(int) $seatId], $voucher === '-' ? null : $voucher);
    echo json_encode(['result' => 'reserved', 'id' => $b->id]);
} catch (ValidationException $e) {
    echo json_encode(['result' => 'conflict']);
}
