<?php

use App\Models\Showtime;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = $app['db']->connection();
$connectionConfig = config('database.connections.'.$connection->getName());
if ($connection->getDatabaseName() !== 'cinema_test' || isset($connectionConfig['read']) || isset($connectionConfig['write'])) {
    throw new RuntimeException('Concession workers must use cinema_test without read/write splitting.');
}
[$script, $userId, $showtimeId, $seatId, $comboId, $role, $barrier] = $argv;

function waitForConcessionReader(string $path): void
{
    $deadline = microtime(true) + 8;
    do {
        clearstatcache(true, $path);
        if (is_file($path)) {
            return;
        }
        usleep(20000);
    } while (microtime(true) < $deadline);
    throw new RuntimeException('The other booking could not read the combo while this transaction held its lock.');
}

try {
    $intercepted = false;
    $overlapped = false;
    DB::listen(function ($event) use (&$intercepted, &$overlapped, $role, $barrier) {
        $sql = strtolower($event->sql);
        if ($intercepted || ! str_starts_with($sql, 'select') || ! str_contains($sql, 'from `concessions`')
            || (! str_contains($sql, 'for update') && ! str_contains($sql, 'lock in share mode') && ! str_contains($sql, 'for share'))) {
            return;
        }
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException('The combo lock must be held inside the booking transaction.');
        }
        $intercepted = true;
        touch($barrier.'/'.$role.'-locked');
        waitForConcessionReader($barrier.'/'.($role === 'first' ? 'second' : 'first').'-locked');
        $overlapped = true;
    });

    $booking = app(BookingService::class)->create(User::findOrFail($userId), Showtime::findOrFail($showtimeId), [$seatId], concessions: [$comboId => 2]);
    if (! $intercepted) {
        throw new RuntimeException('The booking did not lock its combo price read.');
    }
    echo json_encode(['overlapped' => $overlapped, 'subtotal' => (int) $booking->subtotal, 'concessions_total' => (int) $booking->concessions_total]), "\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage()."\n");
    exit(1);
}
