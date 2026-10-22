<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Symfony\Component\Process\Process;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class AccountDeletionRaceTest extends TestCase
{
    use CreatesCinemaBookings, DatabaseMigrations;

    public function test_account_deletion_waits_for_payment_and_preserves_the_receipt(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        [$showtime, $seats] = $this->showtimeWithSeats();
        $service = app(BookingService::class);
        for ($i = 0; $i < 9; $i++) {
            $service->cancel($service->create($user, $showtime, [$seats[0]->id]));
        }
        $booking = $service->create($user, $showtime, [$seats[0]->id]);
        $payment = Payment::create([
            'booking_id' => $booking->id, 'provider' => 'sepay', 'request_id' => 'PAY261023000000ABCDEF',
            'amount' => 80000, 'status' => 'pending',
        ]);
        $barrier = storage_path('framework/testing/deletion-'.bin2hex(random_bytes(8)));
        mkdir($barrier, 0777, true);
        $args = [PHP_BINARY, base_path('tests/Support/account-deletion-worker.php')];
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'cinema_test', 'DB_URL' => '', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array'];
        $delete = new Process([...$args, 'delete', $user->id, $payment->id, $barrier], base_path(), $env, null, 20);
        $callback = new Process([...$args, 'callback', $user->id, $payment->id, $barrier], base_path(), $env, null, 20);
        try {
            $callback->start();
            $delete->start();
            $callback->wait();
            $delete->wait();

            $this->assertTrue($callback->isSuccessful(), $callback->getErrorOutput());
            $this->assertTrue($delete->isSuccessful(), $delete->getErrorOutput());
            $this->assertSame(200, json_decode($callback->getOutput(), true)['status']);
            $this->assertSame(302, json_decode($delete->getOutput(), true)['status']);
            $this->assertSame('/profile', parse_url(json_decode($delete->getOutput(), true)['location'], PHP_URL_PATH));
            $this->assertNotNull($user->fresh());
            $this->assertSame('success', $payment->fresh()->status);
            $this->assertSame('confirmed', $booking->fresh()->status);
            $this->assertSame(8, (int) $user->fresh()->loyalty_points);
        } finally {
            if ($callback->isRunning()) {
                $callback->stop();
            }
            if ($delete->isRunning()) {
                $delete->stop();
            }
            foreach (glob($barrier.'/*') as $file) {
                unlink($file);
            }
            rmdir($barrier);
        }
    }
}
