<?php

use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if ($app['db']->connection()->getDatabaseName() !== 'cinema_test') {
    throw new RuntimeException('Account deletion workers must use cinema_test.');
}
config(['mail.default' => 'array', 'session.driver' => 'array', 'cinema.sepay.api_key' => 'race-key', 'cinema.sepay.account_number' => '123', 'cinema.sepay.bank_code' => 'MBBank']);
[$script, $role, $userId, $paymentId, $barrier] = $argv;
$waitFor = function (array $files): void {
    $deadline = microtime(true) + 10;
    do {
        foreach ($files as $file) {
            clearstatcache(true, $file);
            if (is_file($file)) {
                return;
            }
        }
        usleep(20000);
    } while (microtime(true) < $deadline);
    throw new RuntimeException('Timed out waiting for account deletion test barrier.');
};

try {
    if ($role === 'delete') {
        $user = User::findOrFail($userId);
        Auth::setUser($user);
        $request = Request::create('/profile', 'DELETE', ['password' => 'password']);
        $request->setUserResolver(fn () => $user);
        $request->setLaravelSession($app['session']->driver());
        $request->session()->start();
        $app->instance('request', $request);
        $waitFor([$barrier.'/callback-booking-locked']);

        $bookingsFirst = false;
        DB::connection()->beforeExecuting(function ($query) use (&$bookingsFirst, $barrier) {
            if (str_contains($query, 'from `bookings`') && str_contains($query, 'for update')) {
                $bookingsFirst = true;
                touch($barrier.'/delete-attempting-bookings');
            }
        });
        // Support both lock orders so this same test reproduces the old deadlock.
        DB::listen(function ($event) use (&$bookingsFirst, $barrier, $waitFor) {
            if (! $bookingsFirst && str_contains($event->sql, 'from `users`') && str_contains($event->sql, 'for update')) {
                touch($barrier.'/delete-user-locked');
                $waitFor([$barrier.'/callback-attempting-user']);
            }
        });
        $response = $app->make(ProfileController::class)->destroy($request);
    } else {
        $payment = Payment::findOrFail($paymentId);
        $request = Request::create('/payments/sepay/webhook', 'POST', [
            'accountNumber' => '123', 'transferType' => 'in', 'transferAmount' => (int) $payment->amount,
            'content' => $payment->request_id, 'referenceCode' => 'CONCURRENT-BANK-TRANSFER',
        ]);
        $request->headers->set('Authorization', 'Apikey race-key');
        $app->instance('request', $request);
        DB::connection()->beforeExecuting(function ($query) use ($barrier) {
            if (str_contains($query, 'from `users`') && str_contains($query, 'for update')) {
                touch($barrier.'/callback-attempting-user');
            }
        });
        $paused = false;
        DB::listen(function ($event) use (&$paused, $barrier, $waitFor) {
            if (! $paused && str_contains($event->sql, 'from `bookings`') && str_contains($event->sql, 'for update')) {
                $paused = true;
                touch($barrier.'/callback-booking-locked');
                $waitFor([$barrier.'/delete-user-locked', $barrier.'/delete-attempting-bookings']);
            }
        });
        $response = $app->make(PaymentController::class)->sepayWebhook($request);
    }
    echo json_encode(['status' => $response->getStatusCode(), 'location' => $response->headers->get('Location')]), "\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage()."\n");
    exit(1);
}
