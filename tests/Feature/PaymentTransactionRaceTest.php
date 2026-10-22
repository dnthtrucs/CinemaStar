<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Symfony\Component\Process\Process;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class PaymentTransactionRaceTest extends TestCase
{
    use CreatesCinemaBookings, DatabaseMigrations;

    public function test_concurrent_callbacks_for_one_bank_transaction_confirm_only_one_booking(): void
    {
        $user = User::factory()->create();
        [$showtime, $seats] = $this->showtimeWithSeats();
        $seats[1]->update(['price_surcharge' => 0]);
        $payments = $seats->map(function ($seat, $index) use ($user, $showtime) {
            $booking = app(BookingService::class)->create($user, $showtime, [$seat->id]);

            return Payment::create([
                'booking_id' => $booking->id, 'provider' => 'sepay',
                'request_id' => 'PAY261023000000'.($index === 0 ? 'ABCDEF' : 'UVWXYZ'),
                'amount' => 80000, 'status' => 'pending',
            ]);
        });
        $barrier = storage_path('framework/testing/transaction-'.bin2hex(random_bytes(8)));
        mkdir($barrier, 0777, true);
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'cinema_test', 'DB_URL' => '', 'MAIL_MAILER' => 'array'];
        $processes = $payments->map(fn ($payment, $index) => new Process([
            PHP_BINARY, base_path('tests/Support/payment-transaction-worker.php'), $payment->id,
            $barrier.'/'.$index, $barrier.'/'.(1 - $index),
        ], base_path(), $env, null, 20));
        try {
            $processes->each(fn ($process) => $process->start());
            $processes->each(fn ($process) => $process->wait());
            foreach ($processes as $process) {
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
                $this->assertSame(200, json_decode($process->getOutput(), true)['status']);
            }
            $this->assertSame(1, Payment::where('transaction_id', 'SAME-BANK-TRANSFER')->count());
            $this->assertSame(1, Payment::where('status', 'success')->count());
            $this->assertSame(1, Booking::where('status', 'confirmed')->count());
            $this->assertSame(8, (int) $user->fresh()->loyalty_points);
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            foreach (glob($barrier.'/*') as $file) {
                unlink($file);
            }
            rmdir($barrier);
        }
    }
}
