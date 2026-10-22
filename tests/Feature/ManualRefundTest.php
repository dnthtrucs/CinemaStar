<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\QueryException;
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

    public function test_distinct_transfers_using_the_same_old_qr_are_recorded_once_each(): void
    {
        $this->configureSepay();
        $user = User::factory()->create(['loyalty_points' => 50]);
        $booking = $this->bookingFor($user);
        $this->actingAs($user)->post(route('payments.store', $booking), ['provider' => 'sepay'])->assertRedirect();
        $payment = Payment::firstOrFail();
        $this->post(route('payments.store', $booking), ['provider' => 'sepay', 'points_to_use' => 10])->assertRedirect();

        foreach (['BANK-TRANSFER-A', 'BANK-TRANSFER-B'] as $reference) {
            foreach ([1, 2] as $delivery) {
                $this->transferWithReference($payment, $reference)->assertUnprocessable();
            }
        }

        $refunds = Payment::whereIn('response_code', Payment::manualRefundCodes())->orderBy('id')->get();
        $this->assertSame(['BANK-TRANSFER-A', 'BANK-TRANSFER-B'], $refunds->pluck('transaction_id')->all());
        $this->assertSame(160000, (int) $refunds->sum('amount'));
        $this->assertSame('DUPLICATE_TRANSFER', $refunds->last()->response_code);
        $this->assertSame(2, ActivityLog::where('action', 'like', 'payment.%')->count());
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.dashboard'))
            ->assertOk()->assertSee('BANK-TRANSFER-A')->assertSee('BANK-TRANSFER-B')->assertSee('Chuyển khoản trùng QR');
        $this->assertSame(40, (int) $user->fresh()->loyalty_points);
    }

    public function test_extra_transfer_after_success_does_not_award_points_or_consume_voucher_twice(): void
    {
        $this->configureSepay();
        $voucher = Voucher::create(['code' => 'SAVE', 'name' => 'Test', 'type' => 'fixed', 'value' => 10000]);
        $booking = $this->bookingFor(voucher: 'SAVE');
        $this->actingAs($booking->user)->post(route('payments.store', $booking), ['provider' => 'sepay'])->assertRedirect();
        $payment = Payment::firstOrFail();

        $this->transferWithReference($payment, 'BANK-TRANSFER-A')->assertOk();
        $this->transferWithReference($payment, 'BANK-TRANSFER-A')->assertOk();
        $this->transferWithReference($payment, 'BANK-TRANSFER-B')->assertUnprocessable();
        $this->transferWithReference($payment, 'BANK-TRANSFER-B')->assertUnprocessable();

        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseHas('payments', ['transaction_id' => 'BANK-TRANSFER-B', 'response_code' => 'DUPLICATE_TRANSFER', 'amount' => 70000]);
        $this->assertSame('success', $payment->fresh()->status);
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame(7, (int) $booking->user->fresh()->loyalty_points);
        $this->assertSame(1, (int) $voucher->fresh()->used_count);
        $this->assertDatabaseCount('voucher_usages', 1);
        $this->assertSame(1, ActivityLog::where('action', 'payment.duplicate_transfer')->count());
    }

    public function test_same_provider_transaction_cannot_be_stored_on_two_payments(): void
    {
        $booking = $this->bookingFor();
        $data = ['booking_id' => $booking->id, 'provider' => 'sepay', 'amount' => 80000, 'status' => 'pending', 'transaction_id' => 'BANK-TRANSFER-A'];
        Payment::create($data + ['request_id' => 'PAY261023000000ABCDEF']);

        $this->expectException(QueryException::class);
        Payment::create($data + ['request_id' => 'PAY261023000000UVWXYZ']);
    }

    private function transferWithReference(Payment $payment, string $reference)
    {
        return $this->postJson(route('payments.sepay.webhook'), [
            'accountNumber' => '123', 'transferType' => 'in', 'transferAmount' => (int) $payment->amount,
            'content' => $payment->request_id, 'referenceCode' => $reference,
        ], ['Authorization' => 'Apikey key']);
    }

    public function test_vnpay_extra_transfer_is_recorded_while_successful_order_still_returns_02(): void
    {
        config(['cinema.vnpay.tmn_code' => 'TEST', 'cinema.vnpay.hash_secret' => 'test-secret']);
        $booking = $this->bookingFor();
        $payment = Payment::create(['booking_id' => $booking->id, 'provider' => 'vnpay', 'request_id' => 'PAY261023000000ABCDEF', 'amount' => 80000, 'status' => 'pending']);
        foreach (['VNPAY-A' => '00', 'VNPAY-B' => '02'] as $transactionId => $responseCode) {
            $payload = [
                'vnp_TmnCode' => 'TEST', 'vnp_TxnRef' => $payment->request_id, 'vnp_Amount' => 8000000,
                'vnp_ResponseCode' => '00', 'vnp_TransactionStatus' => '00', 'vnp_TransactionNo' => $transactionId,
            ];
            ksort($payload);
            $payload['vnp_SecureHash'] = hash_hmac('sha512', http_build_query($payload), 'test-secret');
            $this->getJson(route('payments.vnpay.ipn').'?'.http_build_query($payload))->assertOk()->assertJsonPath('RspCode', $responseCode);
            $this->getJson(route('payments.vnpay.ipn').'?'.http_build_query($payload))->assertOk()->assertJsonPath('RspCode', '02');
        }
        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseHas('payments', ['transaction_id' => 'VNPAY-B', 'response_code' => 'DUPLICATE_TRANSFER']);
        $this->assertSame(8, (int) $booking->user->fresh()->loyalty_points);
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
