<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class TicketCheckinTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    public function test_booking_qr_is_public_and_checks_its_signature(): void
    {
        $booking = $this->bookingFor(User::factory()->create(['phone' => '0901234567']));
        $booking->update(['status' => 'confirmed', 'payment_status' => 'paid']);
        $url = route('bookings.verify', ['booking' => $booking, 'signature' => hash_hmac('sha256', $booking->id.'|'.$booking->code, config('app.key'))]);

        $this->get($url)->assertOk()->assertSee($booking->code)->assertSee('Vé hợp lệ')
            ->assertDontSee($booking->user->email)->assertDontSee((string) $booking->user->phone)
            ->assertSee('class="status status-primary"', false);
        $this->get(route('bookings.verify', ['booking' => $booking, 'signature' => 'wrong']))->assertForbidden();
    }

    public function test_unpaid_booking_is_never_shown_as_valid(): void
    {
        $booking = $this->bookingFor();
        $this->actingAs($booking->user)->get(route('bookings.verify', [
            'booking' => $booking, 'signature' => hash_hmac('sha256', $booking->id.'|'.$booking->code, config('app.key')),
        ]))->assertOk()->assertDontSee('Vé hợp lệ')->assertSee('Chưa thanh toán');
    }

    public function test_each_ticket_status_has_a_styled_banner(): void
    {
        $booking = $this->bookingFor();
        $url = $booking->verificationUrl();
        $html = $this->get($url)->assertOk()->getContent();

        // Mỗi badge mà Booking có thể trả về phải có CSS tương ứng trong trang.
        foreach (['primary', 'success', 'warning', 'secondary'] as $badge) {
            $this->assertStringContainsString(".status-{$badge}{", $html);
        }
        $this->assertStringContainsString('class="status status-warning"', $html);
    }
}
