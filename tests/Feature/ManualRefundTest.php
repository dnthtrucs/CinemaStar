<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class ManualRefundTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    private function configureSepay(): void
    {
        config(['cinema.sepay.api_key' => 'key', 'cinema.sepay.account_number' => '123', 'cinema.sepay.bank_code' => 'MBBank']);
    }

    private function transfer(Payment $payment)
    {
        return $this->postJson(route('payments.sepay.webhook'), [
            'accountNumber' => '123', 'transferType' => 'in', 'transferAmount' => (int) $payment->amount,
            'content' => $payment->request_id, 'referenceCode' => 'REF-'.$payment->id,
        ], ['Authorization' => 'Apikey key']);
    }

    public function test_transfer_with_an_outdated_qr_after_redeeming_points_is_flagged(): void
    {
        $this->configureSepay();
        $user = User::factory()->create(['loyalty_points' => 50]);
        $booking = $this->bookingFor($user);
        $this->actingAs($user)->post(route('payments.store', $booking), ['provider' => 'sepay'])->assertRedirect();
        $oldPayment = Payment::firstOrFail();
        $this->post(route('payments.store', $booking), ['provider' => 'sepay', 'points_to_use' => 10])->assertRedirect();
        $this->assertSame(70000, (int) $booking->fresh()->total_price);

        $this->transfer($oldPayment)->assertUnprocessable();
        $this->transfer($oldPayment)->assertUnprocessable();

        $this->assertSame('ORDER_CHANGED', $oldPayment->fresh()->response_code);
        $this->assertSame('pending', $booking->fresh()->status);
        $this->assertSame(1, ActivityLog::where('action', 'payment.order_changed')->count());
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.dashboard'))
            ->assertOk()->assertSee($oldPayment->request_id)->assertSee('Số tiền đơn đã thay đổi');
    }

    public function test_second_payment_for_an_already_paid_booking_is_flagged(): void
    {
        $this->configureSepay();
        $booking = $this->bookingFor();
        $this->actingAs($booking->user)->post(route('payments.store', $booking), ['provider' => 'sepay'])->assertRedirect();
        $this->post(route('payments.store', $booking), ['provider' => 'sepay'])->assertRedirect();
        [$first, $second] = Payment::orderBy('id')->get();

        $this->transfer($first)->assertOk();
        $this->transfer($second)->assertUnprocessable();

        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame('ALREADY_PAID', $second->fresh()->response_code);
    }

    public function test_verified_success_callback_after_gateway_timeout_confirms_the_booking(): void
    {
        config([
            'cinema.payment_mode' => 'production', 'cinema.momo.partner_code' => 'TEST',
            'cinema.momo.access_key' => 'access', 'cinema.momo.secret_key' => 'secret',
            'cinema.momo.url' => 'https://example.invalid/momo',
        ]);
        Http::fake(fn () => throw new ConnectionException('Gateway accepted the request but the response timed out.'));
        $booking = $this->bookingFor();
        $this->actingAs($booking->user)->post(route('payments.store', $booking), ['provider' => 'momo'])->assertRedirect();
        $payment = Payment::firstOrFail();
        $this->assertSame('failed', $payment->status);

        $payload = [
            'amount' => (int) $payment->amount, 'extraData' => '', 'message' => 'Successful',
            'orderId' => $payment->request_id, 'orderInfo' => 'Test', 'orderType' => 'momo_wallet',
            'partnerCode' => 'TEST', 'payType' => 'qr', 'requestId' => $payment->request_id,
            'responseTime' => 1792690000000, 'resultCode' => 0, 'transId' => 'MOMO-1',
        ];
        $raw = collect(['accessKey' => 'access'] + $payload)->map(fn ($value, $key) => $key.'='.$value)->implode('&');
        $payload['signature'] = hash_hmac('sha256', $raw, 'secret');

        $this->postJson(route('payments.momo.ipn'), $payload)->assertOk();
        $this->postJson(route('payments.momo.ipn'), $payload)->assertOk();

        $this->assertSame('success', $payment->fresh()->status);
        $this->assertSame('MOMO-1', $payment->fresh()->transaction_id);
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame('paid', $booking->fresh()->payment_status);
    }
}
