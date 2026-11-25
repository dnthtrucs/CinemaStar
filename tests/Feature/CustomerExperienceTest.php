<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\TicketController;
use App\Models\MovieReview;
use App\Models\Payment;
use App\Models\User;
use App\Services\BookingExchangeService;
use App\Services\BookingService;
use App\Services\MovieRecommendationService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class CustomerExperienceTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    private function exchangeFixture(int $price = 80000): array
    {
        $booking = $this->bookingFor();
        $booking->update(['status' => 'confirmed', 'payment_status' => 'paid', 'expires_at' => null]);
        Payment::create(['booking_id' => $booking->id, 'provider' => 'sepay', 'request_id' => 'ORIGINAL', 'transaction_id' => 'ORIGINAL-REF', 'amount' => $booking->total_price, 'status' => 'success', 'paid_at' => now()]);
        $target = $booking->showtime->replicate();
        $target->starts_at = now()->addDays(2);
        $target->ends_at = now()->addDays(2)->addHours(2);
        $target->base_price = $price;
        $target->save();

        return [$booking, $target, $booking->tickets->pluck('seat_id')->all(), User::factory()->create(['role' => 'admin'])];
    }

    public function test_equal_price_exchange_rotates_barcode_releases_old_seat_and_cannot_replay(): void
    {
        [$b,$s,$ids,$admin] = $this->exchangeFixture();
        $old = $b->barcodeValue();
        $oldShow = $b->showtime_id;
        $service = app(BookingExchangeService::class);
        $ex = $service->request($b, $s, $ids, 'Đổi lịch cá nhân');
        $this->assertSame($old, $b->fresh()->barcodeValue());
        $service->resolve($ex, $admin, true, 'Đã kiểm tra đủ điều kiện', null);
        $this->assertNotSame($old, $b->fresh()->barcodeValue());
        $this->assertEquals($s->id, $b->fresh()->showtime_id);
        $this->assertDatabaseMissing('tickets', ['showtime_id' => $oldShow, 'seat_id' => $ids[0]]);
        $this->assertDatabaseHas('tickets', ['showtime_id' => $s->id, 'seat_id' => $ids[0]]);
        $this->assertSame(1, $b->user->notifications()->where('type', 'booking.exchange')->count());
        $this->expectException(ValidationException::class);
        $service->resolve($ex, $admin, true, 'Lặp lại giao dịch', null);
    }

    public function test_stale_barcode_resolved_before_exchange_cannot_check_in_new_ticket(): void
    {
        [$b,$s,$ids,$admin] = $this->exchangeFixture();
        $svc = app(BookingExchangeService::class);
        $ex = $svc->request($b, $s, $ids, 'Đổi lịch');
        $svc->resolve($ex, $admin, true, 'Kiểm tra điều kiện', null);
        $this->actingAs($admin);
        $method = new \ReflectionMethod(TicketController::class, 'checkInBooking');
        try {
            $method->invoke(app(TicketController::class), $b);
            $this->fail('Stale barcode accepted');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertDatabaseMissing('tickets', ['booking_id' => $b->id, 'status' => 'used']);
    }

    public function test_higher_price_needs_verified_reference_and_keeps_correct_cash_total(): void
    {
        [$b,$s,$ids,$admin] = $this->exchangeFixture(100000);
        $svc = app(BookingExchangeService::class);
        $ex = $svc->request($b, $s, $ids, 'Đổi lịch');
        try {
            $svc->resolve($ex, $admin, true, 'Kiểm tra chứng từ', null);
            $this->fail('Missing reference accepted');
        } catch (ValidationException) {
        }
        $this->assertSame('pending', $ex->fresh()->status);
        $this->assertEquals(80000, $b->fresh()->total_price);
        $svc->resolve($ex, $admin, true, 'Đã nhận thu bù 20000', 'BANK-TOPUP-123');
        $this->assertEquals(100000, $b->fresh()->total_price);
        $this->assertEquals(100000, $b->payments()->where('status', 'success')->sum('amount'));
        $this->assertSame(100000, app(ReportService::class)->revenueSplit()['total']);
    }

    public function test_lower_price_records_refund_adjustment_without_losing_original_receipt(): void
    {
        [$b,$s,$ids,$admin] = $this->exchangeFixture(60000);
        $svc = app(BookingExchangeService::class);
        $ex = $svc->request($b, $s, $ids, 'Đổi lịch');
        $this->assertEquals(-20000, $ex->difference);
        $svc->resolve($ex, $admin, true, 'Đã hoàn 20000 thực tế', 'BANK-REFUND-123');
        $this->assertDatabaseHas('payments', ['booking_id' => $b->id, 'provider' => 'sepay', 'amount' => 80000]);
        $this->assertDatabaseHas('payments', ['booking_id' => $b->id, 'provider' => 'exchange_adjustment', 'amount' => -20000]);
        $this->assertSame(60000, app(ReportService::class)->revenueSplit()['total']);
    }

    public function test_taken_seats_at_approval_leave_old_ticket_intact(): void
    {
        [$b,$s,$ids,$admin] = $this->exchangeFixture();
        $svc = app(BookingExchangeService::class);
        $ex = $svc->request($b, $s, $ids, 'Đổi lịch');
        app(BookingService::class)->create(User::factory()->create(), $s, $ids);
        try {
            $svc->resolve($ex, $admin, true, 'Đã kiểm tra chứng từ', null);
            $this->fail('Occupied seat accepted');
        } catch (ValidationException) {
        }
        $this->assertSame($b->showtime_id, $b->fresh()->showtime_id);
        $this->assertSame('pending', $ex->fresh()->status);
        $this->assertSame(1, $b->tickets()->count());
    }

    public function test_price_change_and_duplicate_request_are_rejected(): void
    {
        [$b,$s,$ids,$admin] = $this->exchangeFixture();
        $svc = app(BookingExchangeService::class);
        $ex = $svc->request($b, $s, $ids, 'Đổi lịch');
        try {
            $svc->request($b, $s, $ids, 'Gửi lặp');
            $this->fail('Duplicate accepted');
        } catch (ValidationException) {
        }
        $s->update(['base_price' => 90000]);
        $this->expectException(ValidationException::class);
        $svc->resolve($ex, $admin, true, 'Duyệt báo giá cũ', null);
    }

    public function test_used_or_near_start_tickets_cannot_be_exchanged(): void
    {
        [$b,$s,$ids,$admin] = $this->exchangeFixture();
        $svc = app(BookingExchangeService::class);
        $b->tickets()->update(['status' => 'used']);
        $this->assertFalse($svc->eligible($b));
        $b->tickets()->update(['status' => 'valid']);
        $b->showtime->update(['starts_at' => now()->addMinutes(45)]);
        $this->assertFalse($svc->eligible($b));
    }

    public function test_exchange_routes_require_owner_admin_and_proof_confirmation(): void
    {
        [$b,$s,$ids,$admin] = $this->exchangeFixture();
        $this->actingAs(User::factory()->create())->get(route('exchanges.create', $b))->assertForbidden();
        $this->actingAs($b->user)->get(route('exchanges.create', [$b, 'target' => $s->id]))->assertOk()->assertSee('Đổi suất');
        $ex = app(BookingExchangeService::class)->request($b, $s, $ids, 'Đổi lịch');
        $this->patch(route('admin.exchanges.resolve', $ex), ['decision' => 'approve', 'note' => 'Thử duyệt'])->assertForbidden();
        $this->actingAs($admin)->patch(route('admin.exchanges.resolve', $ex), ['decision' => 'approve', 'note' => 'Thử duyệt'])->assertSessionHasErrors('verified');
        $this->get(route('admin.exchanges.index'))->assertOk();
    }

    public function test_recommendations_use_own_preferences_and_exclude_stopped_or_unscheduled_movies(): void
    {
        [$show] = $this->showtimeWithSeats();
        $first = $show->movie;
        $first->update(['genre' => 'Hành động']);
        $second = $first->replicate();
        $second->title = 'Phim hài mới';
        $second->slug = 'phim-hai-moi';
        $second->genre = 'Hài';
        $second->save();
        $screen = $show->replicate();
        $screen->movie_id = $second->id;
        $screen->save();
        $a = User::factory()->create(['favorite_genres' => ['Hành động']]);
        $b = User::factory()->create(['favorite_genres' => ['Hài']]);
        $svc = app(MovieRecommendationService::class);
        $this->assertSame($first->id, $svc->forUser($a)->first()->id);
        $this->assertSame($second->id, $svc->forUser($b)->first()->id);
        $second->update(['status' => 'stopped']);
        $this->assertFalse($svc->forUser($b)->contains('id', $second->id));
        $this->actingAs($a)->get('/')->assertOk()->assertSee('Dành cho bạn');
    }

    public function test_history_and_approved_reviews_influence_recommendation_score(): void
    {
        $booking = $this->bookingFor();
        $booking->update(['payment_status' => 'paid', 'status' => 'confirmed']);
        $user = $booking->user;
        MovieReview::create(['movie_id' => $booking->showtime->movie_id, 'user_id' => $user->id, 'rating' => 5, 'status' => 'approved']);
        $result = app(MovieRecommendationService::class)->forUser($user)->first();
        $this->assertSame(1, $result->recommendation_score); // paid genre +2, rating +3, seen -4
        $user->update(['favorite_genres' => ['Hành động']]);
        $this->assertSame(7, app(MovieRecommendationService::class)->forUser($user)->first()->recommendation_score);
    }

    public function test_preferences_can_be_saved_cleared_and_do_not_accept_unknown_genres(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->patch(route('preferences.update'), ['genres' => ['Hài']])->assertRedirect();
        $this->assertSame(['Hài'], $user->fresh()->favorite_genres);
        $this->patch(route('preferences.update'), ['genres' => ['<script>']])->assertSessionHasErrors('genres.0');
        $this->patch(route('preferences.update'), [])->assertRedirect();
        $this->assertSame([], $user->fresh()->favorite_genres);
    }

    public function test_accessibility_positions_require_admin_and_room_ownership(): void
    {
        [$show,$seats] = $this->showtimeWithSeats();
        $user = User::factory()->create();
        $this->actingAs($user)->patch(route('admin.rooms.accessibility', $show->room), ['seats' => [$seats[0]->id]])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->patch(route('admin.rooms.accessibility', $show->room), ['seats' => [$seats[0]->id]])->assertRedirect();
        $this->assertTrue($seats[0]->fresh()->is_accessible);
        $this->get(route('showtimes.show', $show))->assertOk()->assertSee('vị trí hỗ trợ xe lăn');
        $this->patch(route('admin.rooms.accessibility', $show->room), ['seats' => [999999]])->assertUnprocessable();
        $this->assertTrue($seats[0]->fresh()->is_accessible);
    }

    public function test_install_assets_are_public_and_manifest_contains_correct_icons(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('standalone', $manifest['display']);
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }
        $this->assertStringContainsString('offline.html', file_get_contents(public_path('sw.js')));
    }
}
