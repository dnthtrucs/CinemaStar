<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Symfony\Component\Process\Process;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class MembershipConcurrencyTest extends TestCase
{
    use CreatesCinemaBookings, DatabaseMigrations;

    public function test_callbacks_crossing_a_tier_boundary_read_the_preceding_committed_award(): void
    {
        $user = User::factory()->create(['loyalty_points' => 299, 'lifetime_points' => 299]);
        $history = $this->bookingFor($user);
        $history->update(['status' => 'confirmed', 'payment_status' => 'paid', 'points_earned' => 299]);
        $showtime = $history->showtime->replicate();
        $showtime->starts_at = now()->addDays(2);
        $showtime->ends_at = now()->addDays(2)->addMinutes(135);
        $showtime->save();
        $seats = $showtime->room->seats;
        $seats->each(fn ($seat) => $seat->update(['price_surcharge' => 0]));
        $bookings = $seats->map(fn ($seat) => app(BookingService::class)->create($user, $showtime, [$seat->id]));
        $payments = $bookings->map(fn ($booking, $index) => Payment::create([
            'booking_id' => $booking->id, 'provider' => 'sepay',
            'request_id' => 'PAY261024000000'.($index === 0 ? 'ABCDEF' : 'UVWXYZ'),
            'amount' => 80000, 'status' => 'pending',
        ]));
        $barrier = storage_path('framework/testing/membership-'.bin2hex(random_bytes(8)));
        mkdir($barrier, 0777, true);
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'cinema_test', 'DB_URL' => '', 'MAIL_MAILER' => 'array'];
        $processes = $payments->map(fn ($payment, $index) => new Process([
            PHP_BINARY, base_path('tests/Support/membership-worker.php'),
            $payment->id, $index === 0 ? 'first' : 'second', $barrier,
        ], base_path(), $env, null, 20));

        try {
            $processes->each(fn ($process) => $process->start());
            $processes->each(fn ($process) => $process->wait());
            foreach ($processes as $process) {
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
                $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
                $this->assertSame(200, $result['status']);
                $this->assertSame(200, $result['retry_status']);
                $this->assertSame('REPEATABLE-READ', $result['default_isolation']);
            }

            // The first callback uses Bronze at 299; after its commit the second uses Silver at 307.
            $this->assertSame(8, $bookings[0]->fresh()->points_earned);
            $this->assertSame(9, $bookings[1]->fresh()->points_earned);
            $this->assertSame(316, (int) $user->fresh()->loyalty_points);
            $this->assertSame(316, $user->fresh()->lifetimePoints());
            $this->assertSame(2, Payment::where('status', 'success')->count());
            $this->assertDatabaseHas('payments', ['id' => $payments[0]->id, 'transaction_id' => 'MEMBERSHIP-first']);
            $this->assertDatabaseHas('payments', ['id' => $payments[1]->id, 'transaction_id' => 'MEMBERSHIP-second']);
            foreach ($bookings as $booking) {
                $this->assertSame('confirmed', $booking->fresh()->status);
                $this->assertSame('paid', $booking->fresh()->payment_status);
                $this->assertSame(1, $booking->tickets()->count());
            }
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
