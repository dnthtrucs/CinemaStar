<?php

namespace Tests\Concerns;

use App\Models\Booking;
use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Room;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\User;
use App\Services\BookingService;

trait CreatesCinemaBookings
{
    protected function showtimeWithSeats(): array
    {
        $cinema = Cinema::create([
            'name' => 'Rạp Test', 'slug' => 'rap-test', 'location' => 'Hà Nội',
            'city' => 'Hà Nội', 'is_active' => true,
        ]);
        $room = Room::create([
            'cinema_id' => $cinema->id, 'name' => 'Phòng 01', 'type' => '2D',
            'rows' => 3, 'seats_per_row' => 5, 'total_seats' => 15, 'is_active' => true,
        ]);
        $seats = collect([
            Seat::create(['room_id' => $room->id, 'row' => 'A', 'number' => 1, 'type' => 'standard', 'price_surcharge' => 0, 'is_active' => true]),
            Seat::create(['room_id' => $room->id, 'row' => 'C', 'number' => 1, 'type' => 'vip', 'price_surcharge' => 30000, 'is_active' => true]),
        ]);
        $movie = Movie::create([
            'title' => 'Phim Test', 'slug' => 'phim-test', 'description' => 'Nội dung phim dùng để kiểm thử hệ thống.',
            'genre' => 'Hành động', 'director' => 'Đạo diễn Test', 'duration' => 120,
            'age_rating' => 'T13', 'country' => 'Việt Nam', 'language' => 'Tiếng Việt',
            'status' => 'now_showing',
        ]);
        $showtime = Showtime::create([
            'movie_id' => $movie->id, 'room_id' => $room->id,
            'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addMinutes(135),
            'base_price' => 80000, 'format' => '2D', 'language' => 'Tiếng Việt', 'status' => 'scheduled',
        ]);

        return [$showtime, $seats];
    }

    protected function bookingFor(?User $user = null, ?string $voucher = null): Booking
    {
        [$showtime, $seats] = $this->showtimeWithSeats();

        return app(BookingService::class)->create($user ?? User::factory()->create(), $showtime, [$seats[0]->id], $voucher);
    }
}
