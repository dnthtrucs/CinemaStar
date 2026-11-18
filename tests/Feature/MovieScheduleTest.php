<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Services\MovieScheduleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovieScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_dates_publish_and_expire_without_deleting_movies(): void
    {
        $this->travelTo(Carbon::parse('2026-11-05 12:00:00'));
        $make = fn ($slug, $data) => Movie::create(array_merge([
            'title' => $slug, 'slug' => $slug, 'description' => 'Test movie description',
            'genre' => 'Drama', 'director' => 'Test', 'duration' => 120, 'age_rating' => 'P',
            'country' => 'VN', 'language' => 'VN', 'status' => 'upcoming',
            'auto_schedule' => true, 'release_date' => '2026-11-05',
        ], $data));
        $new = $make('new', []);
        $future = $make('future', ['release_date' => '2026-11-06', 'status' => 'now_showing']);
        $lastDay = $make('last-day', ['end_date' => '2026-11-05']);
        $expired = $make('expired', ['release_date' => '2026-11-01', 'end_date' => '2026-11-04']);
        $manual = $make('manual', ['auto_schedule' => false, 'status' => 'stopped']);
        $this->assertSame(4, app(MovieScheduleService::class)->sync());
        $this->assertSame('now_showing', $new->fresh()->status);
        $this->assertSame('upcoming', $future->fresh()->status);
        $this->assertSame('now_showing', $lastDay->fresh()->status);
        $this->assertSame('stopped', $expired->fresh()->status);
        $this->assertSame('stopped', $manual->fresh()->status);
        $this->assertDatabaseCount('movies', 5);
        $this->assertSame(0, app(MovieScheduleService::class)->sync());
        $this->get('/movies')->assertOk()->assertViewHas('movies', fn ($movies) => $movies->count() === 3);
        $this->travelTo(Carbon::parse('2026-11-06 00:00:00'));
        $this->get('/')->assertOk();
        $this->assertSame('stopped', $lastDay->fresh()->status);
        $this->assertSame('now_showing', $future->fresh()->status);
    }
}
