<?php

namespace Tests\Feature;

use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class PaymentConfigurationTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    public function test_an_old_sepay_link_returns_to_checkout_when_gateway_configuration_is_missing(): void
    {
        config(['cinema.sepay.api_key' => null, 'cinema.sepay.account_number' => null, 'cinema.sepay.bank_code' => null]);
        $booking = $this->bookingFor();
        $payment = Payment::create([
            'booking_id' => $booking->id, 'provider' => 'sepay', 'request_id' => 'PAY261025000000CFG123',
            'amount' => $booking->total_price, 'status' => 'initiated',
        ]);
        $this->actingAs($booking->user)->get(route('payments.sepay.show', $payment))
            ->assertRedirect(route('payments.show', $booking))->assertSessionHas('error');
        $this->assertSame('initiated', $payment->fresh()->status);
        $this->assertSame('unpaid', $booking->fresh()->payment_status);
    }
}
