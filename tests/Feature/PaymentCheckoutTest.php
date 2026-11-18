<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class PaymentCheckoutTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    public function test_checkout_hides_simulated_providers_and_rejects_direct_requests(): void
    {
        config(['cinema.payment_mode' => 'simulate']);
        $booking = $this->bookingFor();
        $this->actingAs($booking->user)->get(route('payments.show', $booking))
            ->assertOk()->assertSee('Hoàn tất đặt vé')->assertSee('checkoutCountdown', false)
            ->assertSee('A1')->assertSee('80.000₫')->assertDontSee('MoMo / VNPAY mô phỏng')
            ->assertDontSee('value="momo"', false)->assertDontSee('value="vnpay"', false)->assertSee('payButton', false);
        foreach (['momo', 'vnpay'] as $provider) {
            $this->post(route('payments.store', $booking), ['provider' => $provider])->assertRedirect()->assertSessionHas('error');
        }
        $this->assertDatabaseCount('payments', 0);
        $this->get('/payments/999/simulate')->assertNotFound();
    }

    public function test_unconfigured_live_providers_are_disabled(): void
    {
        config(['cinema.payment_mode' => 'production', 'cinema.momo.partner_code' => '', 'cinema.vnpay.tmn_code' => '', 'cinema.sepay.api_key' => '']);
        $booking = $this->bookingFor();
        $this->actingAs($booking->user)->get(route('payments.show', $booking))->assertOk()->assertSee('Tạm chưa khả dụng');
        $this->post(route('payments.store', $booking), ['provider' => 'sepay'])->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseCount('payments', 0);
    }
}
