<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Showtime;
use App\Services\DeploymentCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class DeploymentCatalogTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    public function test_catalog_preserves_bookings_and_repeated_publish_does_not_duplicate_sessions(): void
    {
        $booking = $this->bookingFor();
        $original = $booking->showtime;
        $cinema = $original->room->cinema;
        $cinemas = [$cinema];
        foreach ([2, 3] as $number) {
            $cinemas[] = Cinema::create(['name' => 'Cinema '.$number, 'slug' => 'cinema-'.$number, 'location' => 'Hanoi', 'city' => 'Hanoi', 'is_active' => true]);
        }
        $movie = $original->movie->replicate();
        $movie->slug = 'new-release';
        $movie->title = 'New release';
        $movie->save();
        $legacy = $original->replicate();
        $legacy->starts_at = now()->addDays(3);
        $legacy->ends_at = now()->addDays(3)->addMinutes(135);
        $legacy->save();
        $start = now()->addDays(2);
        $data = [
            'movies' => [$movie->only($movie->getFillable())],
            'cinemas' => array_map(fn ($c) => $c->only($c->getFillable()), $cinemas),
            'sessions' => [[
                'movie_slug' => $movie->slug, 'cinema_slug' => $cinema->slug, 'room_name' => $original->room->name,
                'starts_at' => $start->toIso8601String(), 'ends_at' => $start->copy()->addMinutes(135)->toIso8601String(),
                'base_price' => 80000, 'format' => '2D', 'language' => 'Vietnamese', 'subtitle' => null,
            ]],
        ];
        $service = app(DeploymentCatalogService::class);
        $this->assertSame(1, $service->sync($data)['sessions_added']);
        $this->assertSame(0, $service->sync($data)['sessions_added']);
        $this->assertSame('scheduled', $original->fresh()->status);
        $this->assertSame('cancelled', $legacy->fresh()->status);
        $this->assertSame($original->id, $booking->fresh()->showtime_id);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(1, Showtime::where('movie_id', $movie->id)->count());
        $this->assertSame(2, Movie::count());
    }

    public function test_packaged_banner_uses_a_public_asset_and_uploads_keep_the_storage_path(): void
    {
        $banner = new Banner(['image_path' => '/catalog-assets/example.webp']);
        $this->assertSame(asset('catalog-assets/example.webp'), $banner->image_url);
        $banner->image_path = 'banners/upload.webp';
        $this->assertSame(asset('storage/banners/upload.webp'), $banner->image_url);
    }
}
