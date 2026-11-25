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
            $target = $showtime;
            if ($index === 1) {
                $target = $showtime->replicate();
                $target->starts_at = now()->addDays(2);
                $target->ends_at = now()->addDays(2)->addHours(2);
                $target->save();
            }
            $booking = app(BookingService::class)->create($user, $target, [$seat->id]);

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

    public function test_cancellation_racing_with_bank_confirmation_never_loses_the_transfer(): void
    {
        $b = $this->bookingFor();
        $payment = Payment::create(['booking_id' => $b->id, 'provider' => 'sepay', 'request_id' => 'PAY261126000000ABCDEF', 'amount' => 80000, 'status' => 'pending']);
        $dir = storage_path('framework/testing/cancel-race-'.bin2hex(random_bytes(8)));
        mkdir($dir, 0777, true);
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'cinema_test', 'DB_URL' => '', 'MAIL_MAILER' => 'array'];
        $processes = [];
        foreach (['payment-transaction-worker.php', 'cancellation-race-worker.php'] as $i => $script) {
            $processes[] = new Process([PHP_BINARY, base_path('tests/Support/'.$script), $payment->id, $dir.'/'.$i, $dir.'/'.(1 - $i)], base_path(), $env, null, 30);
        }
        try {
            foreach ($processes as $process) {
                $process->start();
            }
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            }
            $this->assertSame('cancelled', $b->showtime->fresh()->status);
            $this->assertSame('SAME-BANK-TRANSFER', $payment->fresh()->transaction_id);
            $this->assertTrue($payment->fresh()->status === 'success' || $payment->fresh()->needsManualRefund());
            $this->assertSame(1, Payment::where('transaction_id', 'SAME-BANK-TRANSFER')->count());
            $this->actingAs(User::factory()->create(['role' => 'admin']))->patch(route('admin.tickets.update', $b))->assertUnprocessable();
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            foreach (glob($dir.'/*') as $file) {
                unlink($file);
            }
            rmdir($dir);
        }
    }
}
