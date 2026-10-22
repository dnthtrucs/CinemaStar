<?php

use App\Http\Controllers\PaymentController;
use App\Models\Payment;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if ($app['db']->connection()->getDatabaseName() !== 'cinema_test') {
    throw new RuntimeException('Payment transaction workers must use cinema_test.');
}
config(['mail.default' => 'array', 'cinema.sepay.api_key' => 'race-key', 'cinema.sepay.account_number' => '123', 'cinema.sepay.bank_code' => 'MBBank']);
[$script, $paymentId, $readyFile, $otherReadyFile] = $argv;
try {
    $payment = Payment::findOrFail($paymentId);
    $request = Request::create('/payments/sepay/webhook', 'POST', [
        'accountNumber' => '123', 'transferType' => 'in', 'transferAmount' => (int) $payment->amount,
        'content' => $payment->request_id, 'referenceCode' => 'SAME-BANK-TRANSFER',
    ]);
    $request->headers->set('Authorization', 'Apikey race-key');
    $app->instance('request', $request);
    // Both callbacks finish the receipt lookup before either writes a bank transaction.
    DB::listen(function ($event) use ($readyFile, $otherReadyFile) {
        if (str_contains($event->sql, 'count(*)') && str_contains($event->sql, 'from `tickets`')) {
            touch($readyFile);
            $deadline = microtime(true) + 10;
            do {
                clearstatcache(true, $otherReadyFile);
                if (is_file($otherReadyFile)) {
                    return;
                }
                usleep(20000);
            } while (microtime(true) < $deadline);
            throw new RuntimeException('Timed out waiting for concurrent payment callback.');
        }
    });
    $response = $app->make(PaymentController::class)->sepayWebhook($request);
    echo json_encode(['status' => $response->getStatusCode()]), "\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage()."\n");
    exit(1);
}
