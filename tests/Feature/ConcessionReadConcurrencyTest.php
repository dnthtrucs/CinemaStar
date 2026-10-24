<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Concession;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Symfony\Component\Process\Process;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class ConcessionReadConcurrencyTest extends TestCase
{
    use CreatesCinemaBookings, DatabaseMigrations;

    public function test_distinct_screenings_can_hold_the_same_combo_read_lock_at_the_same_time(): void
    {
        [$firstShowtime, $firstSeats] = $this->showtimeWithSeats();
        $secondRoom = Room::findOrFail($firstShowtime->room_id)->replicate();
        $secondRoom->name = 'Phòng 02';
        $secondRoom->save();
        $secondSeat = $firstSeats[0]->replicate();
        $secondSeat->room_id = $secondRoom->id;
        $secondSeat->save();
        $secondShowtime = $firstShowtime->replicate();
        $secondShowtime->room_id = $secondRoom->id;
        $secondShowtime->save();
        $combo = Concession::create(['name' => 'Combo đọc đồng thời', 'price' => 50000, 'is_active' => true]);
        $users = User::factory()->count(2)->create();
        $barrier = storage_path('framework/testing/concession-read-'.bin2hex(random_bytes(8)));
        mkdir($barrier, 0777, true);
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'cinema_test', 'DB_URL' => ''];
        $requests = [[$users[0]->id, $firstShowtime->id, $firstSeats[0]->id, 'first'], [$users[1]->id, $secondShowtime->id, $secondSeat->id, 'second']];
        $processes = array_map(fn (array $request) => new Process([
            PHP_BINARY, base_path('tests/Support/concession-read-worker.php'),
            $request[0], $request[1], $request[2], $combo->id, $request[3], $barrier,
        ], base_path(), $env, null, 25), $requests);

        try {
            foreach ($processes as $process) {
                $process->start();
            }
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
                $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
                $this->assertTrue($result['overlapped']);
                $this->assertSame(180000, $result['subtotal']);
                $this->assertSame(100000, $result['concessions_total']);
            }
            $this->assertDatabaseCount('bookings', 2);
            $this->assertDatabaseCount('booking_concessions', 2);
            $this->assertSame(2, Booking::whereIn('showtime_id', [$firstShowtime->id, $secondShowtime->id])->distinct()->count('showtime_id'));
            foreach (Booking::with('concessions')->get() as $booking) {
                $this->assertSame(2, (int) $booking->concessions->first()->pivot->quantity);
                $this->assertSame(50000, (int) $booking->concessions->first()->pivot->unit_price);
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
