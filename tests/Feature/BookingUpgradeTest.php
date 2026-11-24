<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class BookingUpgradeTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    public function test_availability_hides_customer_data_and_releases_expired_seats(): void
    {
        $booking = $this->bookingFor();
        $url = route('showtimes.availability', $booking->showtime);
        $this->getJson($url)->assertOk()->assertJsonPath('open', true)->assertJsonPath('unavailable.0', $booking->tickets->first()->seat_id)
            ->assertDontSee($booking->user->email)->assertHeader('Cache-Control', 'no-store, private');
        $booking->update(['expires_at' => now()->subMinute()]);
        $this->getJson($url)->assertOk()->assertJsonPath('unavailable', []);
        $this->assertSame('expired', $booking->fresh()->status);
        $booking->showtime->update(['status' => 'cancelled']);
        $this->getJson($url)->assertJsonPath('open', false);
    }

    public function test_notification_is_private_and_read_access_is_scoped_to_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $item = $owner->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'booking.confirmed', 'data' => ['title' => 'Private ticket', 'message' => 'Owner only']]);
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
        $this->actingAs($other)->get(route('notifications.index'))->assertDontSee('Owner only');
        $this->post(route('notifications.read', $item->id))->assertNotFound();
        $this->actingAs($owner)->get(route('notifications.index'))->assertSee('Owner only');
        $this->post(route('notifications.read', $item->id))->assertRedirect();
        $this->assertNotNull($item->fresh()->read_at);
    }

    public function test_successful_payment_stores_one_notification_even_when_email_fails(): void
    {
        $this->configureSepayPayments();
        $booking = $this->bookingFor();
        $payment = Payment::create(['booking_id' => $booking->id, 'provider' => 'sepay', 'request_id' => 'PAY261124123456ABCDEF', 'amount' => $booking->total_price, 'status' => 'initiated']);
        Mail::shouldReceive('mailer')->andThrow(new \RuntimeException('Mail unavailable for test'));
        $this->confirmSepayPayment($payment)->assertOk();
        $this->confirmSepayPayment($payment)->assertOk();
        $this->assertSame('paid', $booking->fresh()->payment_status);
        $this->assertSame(1, $booking->user->notifications()->where('type', 'booking.confirmed')->count());
        $this->assertEquals($booking->id, $booking->user->notifications()->first()->data['booking_id']);
    }
}
