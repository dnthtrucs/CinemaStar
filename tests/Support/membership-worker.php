<?php

use App\Http\Controllers\PaymentController;
use App\Models\Payment;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = $app['db']->connection();
$connectionConfig = config('database.connections.'.$connection->getName());
if ($connection->getDatabaseName() !== 'cinema_test' || isset($connectionConfig['read']) || isset($connectionConfig['write'])) {
    throw new RuntimeException('Membership workers must use cinema_test without read/write splitting.');
}
config(['mail.default' => 'array', 'cinema.sepay.api_key' => 'membership-race-key', 'cinema.sepay.account_number' => '123', 'cinema.sepay.bank_code' => 'MBBank']);
[$script, $paymentId, $role, $barrier] = $argv;

function waitForMembershipBarrier(string $path): void
{
    $deadline = microtime(true) + 10;
    do {
        clearstatcache(true, $path);
        if (is_file($path)) {
            return;
        }
        usleep(20000);
    } while (microtime(true) < $deadline);
    throw new RuntimeException('Timed out waiting for membership callback barrier: '.basename($path));
}

try {
    // Reproduce the deployed MySQL default independently of a developer's server settings.
    DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $payment = Payment::findOrFail($paymentId);
    $request = Request::create('/payments/sepay/webhook', 'POST', [
        'accountNumber' => '123', 'transferType' => 'in', 'transferAmount' => (int) $payment->amount,
        'content' => $payment->request_id, 'referenceCode' => 'MEMBERSHIP-'.$role,
    ]);
    $request->headers->set('Authorization', 'Apikey membership-race-key');
    $app->instance('request', $request);
    $snapshotRead = false;
    $intercepted = false;
    $firstLockReported = false;
    DB::listen(function ($event) use (&$snapshotRead, &$firstLockReported, $role, $barrier) {
        if (str_starts_with(strtolower($event->sql), 'select')
            && str_contains($event->sql, 'from `payments`')
            && str_contains($event->sql, '`transaction_id`')
            && ! str_contains($event->sql, 'for update')) {
            $snapshotRead = true;
        }
        if ($role === 'first' && ! $firstLockReported
            && str_contains($event->sql, 'from `users`') && str_contains($event->sql, 'for update')) {
            $firstLockReported = true;
            touch($barrier.'/first-user-locked');
        }
    });
    DB::connection()->beforeExecuting(function ($sql) use (&$intercepted, &$snapshotRead, $role, $barrier) {
        if ($intercepted || ! str_contains($sql, 'from `users`') || ! str_contains($sql, 'for update')) {
            return;
        }
        if (! $snapshotRead) {
            throw new RuntimeException('Membership barrier was reached before the receipt snapshot read.');
        }
        $intercepted = true;
        if ($role === 'second') {
            // B already has its old snapshot before A may lock the user and award points.
            touch($barrier.'/second-before-user');
            waitForMembershipBarrier($barrier.'/first-user-locked');
        } else {
            waitForMembershipBarrier($barrier.'/second-before-user');
        }
    });

    $controller = $app->make(PaymentController::class);
    $response = $controller->sepayWebhook($request);
    $retry = $controller->sepayWebhook($request);
    $isolation = DB::selectOne('SELECT @@session.transaction_isolation AS isolation_level')->isolation_level;
    echo json_encode(['status' => $response->getStatusCode(), 'retry_status' => $retry->getStatusCode(), 'default_isolation' => $isolation]), "\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage()."\n");
    exit(1);
}
