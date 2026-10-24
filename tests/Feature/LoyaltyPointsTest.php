<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use App\Models\Voucher;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class LoyaltyPointsTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    public function test_redeemed_points_are_stored_and_not_deducted_twice(): void
    {
        config(['cinema.payment_mode' => 'simulate']);
        $user = User::factory()->create(['loyalty_points' => 50]);
        $booking = $this->bookingFor($user);

        foreach ([1, 2] as $attempt) {
            $this->actingAs($user)->post(route('payments.store', $booking), [
                'provider' => 'momo', 'points_to_use' => 10,
            ])->assertRedirect();
            $this->assertSame(10, $booking->fresh()->points_used);
            $this->assertSame(40, (int) $user->fresh()->loyalty_points);
            $this->assertSame(70000, (int) $booking->fresh()->total_price);
        }
    }

    public function test_cancellation_returns_reserved_points(): void
    {
        config(['cinema.payment_mode' => 'simulate']);
        $user = User::factory()->create(['loyalty_points' => 50]);
        $booking = $this->bookingFor($user);
        $this->actingAs($user)->post(route('payments.store', $booking), [
            'provider' => 'momo', 'points_to_use' => 10,
        ])->assertRedirect();

        app(BookingService::class)->cancel($booking);

        $this->assertSame(50, (int) $user->fresh()->loyalty_points);
        $this->assertSame(0, $booking->fresh()->points_used);
    }

    public function test_booking_keeps_its_voucher(): void
    {
        $voucher = Voucher::create(['code' => 'SAVE', 'name' => 'Test', 'value' => 10000, 'type' => 'fixed']);
        $booking = $this->bookingFor(voucher: $voucher->code);
        $this->assertSame($voucher->id, $booking->fresh()->voucher_id);
    }

    public function test_successful_payment_awards_points_once(): void
    {
        Notification::fake();
        config(['cinema.sepay.api_key' => 'key', 'cinema.sepay.account_number' => '123', 'cinema.sepay.bank_code' => 'MBBank']);
        $user = User::factory()->create(['loyalty_points' => 50]);
        $booking = $this->bookingFor($user);
        $this->actingAs($user)->post(route('payments.store', $booking), ['provider' => 'sepay'])->assertRedirect();
        $payment = Payment::firstOrFail();

        foreach ([1, 2] as $attempt) {
            $this->postJson(route('payments.sepay.webhook'), [
                'accountNumber' => '123', 'transferType' => 'in', 'transferAmount' => 80000,
                'content' => $payment->request_id, 'referenceCode' => 'TX1',
            ], ['Authorization' => 'Apikey key'])->assertOk();
        }

        $this->assertSame(8, $booking->fresh()->points_earned);
        $this->assertSame(58, (int) $user->fresh()->loyalty_points);
    }
}
