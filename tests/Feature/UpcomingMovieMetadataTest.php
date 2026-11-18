<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class UpcomingMovieMetadataTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    public function test_unannounced_metadata_is_not_shown_as_a_fake_duration_or_age_rating(): void
    {
        [$showtime] = $this->showtimeWithSeats();
        $movie = $showtime->movie;
        $movie->update(['duration' => 0, 'age_rating' => 'NO', 'status' => 'upcoming', 'release_date' => '2027-02-26']);
        $this->get(route('movies.show', $movie))->assertOk()->assertSee('Thời lượng chưa công bố')->assertSee('Chưa phân loại')->assertSee('26/02/2027')->assertDontSee('>0 phút<', false);
        $this->get(route('movies.index'))->assertOk()->assertSee('Thời lượng chưa công bố');
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('admin.showtimes.store'), [
            'movie_id' => $movie->id, 'room_id' => $showtime->room_id, 'starts_at' => now()->addDays(4)->format('Y-m-d H:i:s'),
            'base_price' => 80000, 'format' => '2D', 'language' => 'Tiếng Việt', 'status' => 'scheduled',
        ])->assertSessionHasErrors('movie_id');
    }
}
