<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class NotificationExperienceTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    public function test_feed_and_bulk_read_are_private_and_never_include_other_users(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        foreach ([$a, $b] as $user) {
            $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'booking.confirmed', 'data' => ['title' => 'Secret '.$user->id]]);
        }
        $this->getJson(route('notifications.feed'))->assertUnauthorized();
        $this->actingAs($a)->getJson(route('notifications.feed'))->assertOk()->assertJsonPath('unread', 1)->assertJsonPath('items.0.title', 'Secret '.$a->id)->assertDontSee('Secret '.$b->id)->assertHeader('Cache-Control', 'no-store, private');
        $this->post(route('notifications.read-all'))->assertRedirect(route('notifications.index'));
        $this->assertSame(0, $a->unreadNotifications()->count());
        $this->assertSame(1, $b->unreadNotifications()->count());
        $this->getJson(route('notifications.feed'))->assertJsonPath('unread', 0)->assertJsonPath('items', []);
    }

    public function test_booking_filters_only_show_owned_matching_orders(): void
    {
        $booking = $this->bookingFor();
        $other = User::factory()->create();
        $this->actingAs($booking->user)->get(route('bookings.index', ['filter' => 'unpaid']))->assertOk()->assertSee($booking->code);
        $this->get(route('bookings.index', ['filter' => 'upcoming']))->assertOk()->assertDontSee($booking->code);
        $booking->update(['status' => 'confirmed', 'payment_status' => 'paid']);
        $this->get(route('bookings.index', ['filter' => 'upcoming']))->assertOk()->assertSee($booking->code);
        $this->get(route('bookings.index', ['filter' => 'unpaid']))->assertOk()->assertDontSee($booking->code);
        $booking->showtime->update(['starts_at' => now()->subDay()]);
        $this->get(route('bookings.index', ['filter' => 'history']))->assertOk()->assertSee($booking->code);
        $this->actingAs($other)->get(route('bookings.index', ['filter' => 'history']))->assertOk()->assertDontSee($booking->code);
    }
}
