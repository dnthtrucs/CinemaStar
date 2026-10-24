<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Showtime;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class ReportTicketRevenueTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    public function test_top_movies_rank_ticket_cash_and_reconcile_with_the_revenue_split(): void
    {
        [$first] = $this->showtimeWithSeats();
        $first->movie->update(['title' => 'Phim A nhiều combo']);
        $movie = $first->movie->replicate();
        $movie->fill(['title' => 'Phim B nhiều vé', 'slug' => 'phim-b-nhieu-ve'])->save();
        $second = $first->replicate();
        $second->movie_id = $movie->id;
        $second->save();
        $customer = User::factory()->create();

        // A receives more cash overall, but only 24,000 belongs to tickets.
        $this->paidBooking($customer, $first, 400000, 320000, [100000, 20000]);
        // B's post-discount ticket revenue is 76,800, so B must rank first.
        $this->paidBooking($customer, $second, 100000, 20000, [96000]);
        // Per-order rounding must stay identical in both aggregate reports.
        $this->paidBooking($customer, $first, 100000, 33333, [56001, 14000]);
        $this->paidBooking($customer, $first, 400000, 0, [900000], 'refunded');
        $this->paidBooking($customer, $first, 400000, 0, [900000], 'unpaid');

        $service = app(ReportService::class);
        $movies = $service->topMovies();
        $split = $service->revenueSplit();

        $this->assertCount(2, $movies);
        $this->assertSame($second->movie_id, $movies[0]['movie_id']);
        $this->assertSame(76800, $movies[0]['revenue']);
        $this->assertSame($first->movie_id, $movies[1]['movie_id']);
        $this->assertSame(70668, $movies[1]['revenue']);
        $this->assertSame($split['tickets'], array_sum(array_column($movies, 'revenue')));
        $this->assertSame(286001, $split['total']);
        $this->assertSame($split['total'], $split['tickets'] + $split['concessions']);
    }

    public function test_dashboard_labels_the_movie_ranking_as_ticket_revenue(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.dashboard'))->assertOk()->assertSee('Top phim theo doanh thu vé');
        $this->assertStringContainsString("label: 'Doanh thu vé'", file_get_contents(resource_path('js/admin-dashboard.js')));
    }

    private function paidBooking(User $user, Showtime $showtime, int $subtotal, int $concessions, array $amounts, string $status = 'paid'): void
    {
        $booking = Booking::create([
            'user_id' => $user->id, 'showtime_id' => $showtime->id, 'code' => 'BK'.Str::upper(Str::random(16)),
            'quantity' => 1, 'subtotal' => $subtotal, 'concessions_total' => $concessions,
            'discount' => 0, 'total_price' => array_sum($amounts), 'status' => 'confirmed', 'payment_status' => $status,
        ]);
        foreach ($amounts as $amount) {
            Payment::create([
                'booking_id' => $booking->id, 'provider' => 'sepay', 'request_id' => (string) Str::uuid(),
                'transaction_id' => (string) Str::uuid(), 'amount' => $amount, 'status' => 'success', 'paid_at' => now(),
            ]);
        }
    }
}
