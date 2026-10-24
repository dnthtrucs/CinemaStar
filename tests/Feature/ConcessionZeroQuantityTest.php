<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Concession;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class ConcessionZeroQuantityTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    public static function entryPoints(): array
    {
        return ['HTTP form' => ['http'], 'service' => ['service']];
    }

    #[DataProvider('entryPoints')]
    public function test_zero_lines_are_ignored_before_checking_availability(string $entryPoint): void
    {
        [$showtime, $seats] = $this->showtimeWithSeats();
        $user = User::factory()->create();
        $active = Concession::create(['name' => 'Combo được chọn', 'price' => 20000, 'is_active' => true]);
        $inactive = Concession::create(['name' => 'Combo không chọn', 'price' => 99000, 'is_active' => false]);
        $selection = [$active->id => 2, $inactive->id => 0, 999999 => 0];

        if ($entryPoint === 'http') {
            $this->actingAs($user)->post(route('bookings.store', $showtime), [
                'seats' => [$seats[0]->id], 'concessions' => $selection,
            ])->assertRedirect();
            $booking = Booking::firstOrFail();
        } else {
            $booking = app(BookingService::class)->create($user, $showtime, [$seats[0]->id], concessions: $selection);
        }

        $this->assertSame(40000, (int) $booking->concessions_total);
        $this->assertSame(120000, (int) $booking->subtotal);
        $this->assertSame([$active->id], $booking->concessions()->pluck('concessions.id')->all());
        $this->assertDatabaseHas('booking_concessions', ['booking_id' => $booking->id, 'quantity' => 2, 'unit_price' => 20000]);
    }

    #[DataProvider('entryPoints')]
    public function test_six_posted_types_with_only_five_positive_types_are_valid(string $entryPoint): void
    {
        [$showtime, $seats] = $this->showtimeWithSeats();
        $user = User::factory()->create();
        $selection = [];
        for ($index = 0; $index < 6; $index++) {
            $combo = Concession::create(['name' => 'Combo '.$index, 'price' => 10000, 'is_active' => true]);
            $selection[$combo->id] = $index === 5 ? 0 : 1;
        }

        if ($entryPoint === 'http') {
            $this->actingAs($user)->post(route('bookings.store', $showtime), [
                'seats' => [$seats[0]->id], 'concessions' => $selection,
            ])->assertRedirect();
            $booking = Booking::firstOrFail();
        } else {
            $booking = app(BookingService::class)->create($user, $showtime, [$seats[0]->id], concessions: $selection);
        }

        $this->assertSame(5, $booking->concessions()->count());
        $this->assertSame(50000, (int) $booking->concessions_total);
        $this->assertSame(130000, (int) $booking->total_price);
    }

    #[DataProvider('entryPoints')]
    public function test_six_positive_types_are_rejected(string $entryPoint): void
    {
        [$showtime, $seats] = $this->showtimeWithSeats();
        $user = User::factory()->create();
        $selection = [];
        for ($index = 0; $index < 6; $index++) {
            $combo = Concession::create(['name' => 'Combo '.$index, 'price' => 10000, 'is_active' => true]);
            $selection[$combo->id] = 1;
        }

        if ($entryPoint === 'http') {
            $this->actingAs($user)->postJson(route('bookings.store', $showtime), [
                'seats' => [$seats[0]->id], 'concessions' => $selection,
            ])->assertUnprocessable()->assertJsonValidationErrors('concessions');
        } else {
            try {
                app(BookingService::class)->create($user, $showtime, [$seats[0]->id], concessions: $selection);
                $this->fail('Six positive combo types must fail validation.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('concessions', $exception->errors());
            }
        }
        $this->assertDatabaseCount('bookings', 0);
    }
}
