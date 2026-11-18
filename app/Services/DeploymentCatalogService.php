<?php

namespace App\Services;

use App\Models\Banner;
use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Room;
use App\Models\Showtime;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DeploymentCatalogService
{
    public function sync(array $data): array
    {
        if (count($data['cinemas'] ?? []) !== 3 || empty($data['movies'])) {
            throw new \RuntimeException('Incomplete deployment catalog.');
        }
        $report = DB::transaction(function () use ($data) {
            $movies = [];
            foreach ($data['movies'] as $values) {
                $movie = Movie::firstOrNew(['slug' => $values['slug']]);
                $movie->fill($values)->save();
                $movies[$movie->slug] = $movie;
            }
            $cinemas = [];
            foreach ($data['cinemas'] as $values) {
                // Use the existing three cinemas. Never attach a session to another cinema.
                $cinema = Cinema::where('slug', $values['slug'])->firstOrFail();
                $cinema->fill($values)->save();
                $cinemas[$cinema->slug] = $cinema;
            }
            // Preserve bookings and their original showtimes; retire only unused sample sessions.
            Showtime::whereHas('movie', fn ($q) => $q->whereNotIn('slug', array_keys($movies)))
                ->where('starts_at', '>', now())->whereDoesntHave('bookings')
                ->where('status', 'scheduled')->update(['status' => 'cancelled']);
            Movie::whereNotIn('slug', array_keys($movies))->update(['status' => 'stopped', 'auto_schedule' => false, 'is_featured' => false]);
            $added = 0;
            $skipped = 0;
            foreach ($data['sessions'] ?? [] as $values) {
                $movie = $movies[$values['movie_slug']] ?? null;
                $cinema = $cinemas[$values['cinema_slug']] ?? null;
                $start = Carbon::parse($values['starts_at']);
                $end = Carbon::parse($values['ends_at']);
                if (! $movie || ! $cinema || $movie->duration < 30 || $movie->age_rating === 'NO' || $start->isPast() || $end->lte($start)) {
                    $skipped++;

                    continue;
                }
                $room = Room::where('cinema_id', $cinema->id)->where('name', $values['room_name'])->where('is_active', true)->firstOrFail();
                if (Showtime::where('room_id', $room->id)->where('movie_id', $movie->id)->where('starts_at', $start)->exists()) {
                    continue;
                }
                if (Showtime::where('room_id', $room->id)->where('status', '!=', 'cancelled')->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists()) {
                    $skipped++;

                    continue;
                }
                Showtime::create(['movie_id' => $movie->id, 'room_id' => $room->id, 'starts_at' => $start, 'ends_at' => $end,
                    'base_price' => $values['base_price'], 'format' => $values['format'], 'language' => $values['language'],
                    'subtitle' => $values['subtitle'], 'status' => 'scheduled']);
                $added++;
            }
            foreach ($data['banners'] ?? [] as $values) {
                Banner::updateOrCreate(['title' => $values['title']], $values);
            }

            return ['movies' => count($movies), 'cinemas' => count($cinemas), 'sessions_added' => $added, 'sessions_skipped' => $skipped];
        });
        Cache::forget('home-page-data:v1');

        return $report;
    }
}
