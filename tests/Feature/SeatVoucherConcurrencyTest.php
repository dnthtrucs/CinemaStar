<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Symfony\Component\Process\Process;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class SeatVoucherConcurrencyTest extends TestCase
{
    use CreatesCinemaBookings, DatabaseMigrations;

    public function test_two_processes_cannot_reserve_the_same_seat(): void
    {
        $this->race(false);
    }

    public function test_two_processes_cannot_reserve_the_last_voucher_use(): void
    {
        $this->race(true);
    }

    private function race(bool $voucher): void
    {
        [$s,$seats] = $this->showtimeWithSeats();
        if ($voucher) {
            Voucher::create(['code' => 'LAST', 'name' => 'Last use', 'type' => 'fixed', 'value' => 10000, 'usage_limit' => 1]);
        }
        $showtimeIds = [$s->id, $s->id];
        if ($voucher) {
            $other = $s->replicate();
            $other->starts_at = now()->addDays(2);
            $other->ends_at = now()->addDays(2)->addHours(2);
            $other->save();
            $showtimeIds[1] = $other->id;
        }
        $users = [User::factory()->create(), User::factory()->create()];
        $dir = storage_path('framework/testing/seat-race-'.bin2hex(random_bytes(8)));
        mkdir($dir, 0777, true);
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'cinema_test', 'DB_URL' => '', 'MAIL_MAILER' => 'array'];
        $processes = [];
        for ($i = 0; $i < 2; $i++) {
            $processes[] = new Process([PHP_BINARY, base_path('tests/Support/booking-race-worker.php'), $users[$i]->id, $showtimeIds[$i], $seats[$voucher ? $i : 0]->id, $voucher ? 'LAST' : '-', $dir.'/'.$i, $dir.'/'.(1 - $i)], base_path(), $env, null, 30);
        }
        try {
            foreach ($processes as $process) {
                $process->start();
            }
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            }
            $results = array_map(fn ($p) => json_decode($p->getOutput(), true)['result'], $processes);
            sort($results);
            $this->assertSame(['conflict', 'reserved'], $results);
            $this->assertSame(1, Booking::count());
            $this->assertSame(1, Ticket::count());
            if ($voucher) {
                $this->assertSame(0, (int) Voucher::first()->used_count);
            }
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            foreach (glob($dir.'/*') as $file) {
                unlink($file);
            }rmdir($dir);
        }
    }
}
