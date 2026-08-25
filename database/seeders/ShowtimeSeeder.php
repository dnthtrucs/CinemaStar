<?php

namespace Database\Seeders;

use App\Models\Movie;
use App\Models\Room;
use App\Models\Showtime;
use Illuminate\Database\Seeder;

class ShowtimeSeeder extends Seeder
{
    public function run(): void
    {
        $movies = Movie::where('status', 'now_showing')->get();
        $rooms = Room::with('cinema')->get();

        foreach ($rooms as $roomIndex => $room) {
            foreach (range(0, 4) as $day) {
                foreach ([10, 14, 19] as $slotIndex => $hour) {
                    $movie = $movies[($roomIndex + $day + $slotIndex) % $movies->count()];
                    $start = now()->addDays($day + 1)->setTime($hour, 0);

                    Showtime::updateOrCreate(
                        ['room_id' => $room->id, 'starts_at' => $start],
                        [
                            'movie_id' => $movie->id,
                            'ends_at' => $start->copy()->addMinutes($movie->duration + 15),
                            'base_price' => $hour >= 18 ? 100000 : 80000,
                            'format' => $room->type,
                            'language' => 'Tiếng Anh',
                            'subtitle' => 'Phụ đề Việt',
                            'status' => 'scheduled',
                        ]
                    );
                }
            }
        }
    }
}
