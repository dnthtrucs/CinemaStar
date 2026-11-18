<?php

namespace Tests\Feature;

use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class SepayReconciliationTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    private function setupPayment(): Payment
    {
        config(['cinema.sepay.api_token' => 'test-token', 'cinema.sepay.account_number' => '123456789', 'cinema.sepay.reconcile_enabled' => true]);
        Notification::fake();
        $booking = $this->bookingFor();

        return Payment::create(['booking_id' => $booking->id, 'provider' => 'sepay', 'request_id' => 'PAY261105223609ABCDEF', 'amount' => $booking->total_price, 'status' => 'pending']);
    }

    private function transfer(Payment $payment): array
    {
        return ['account_number' => '123456789', 'transaction_content' => 'CT '.$payment->request_id, 'amount_in' => $payment->amount, 'amount_out' => '0.00', 'reference_number' => 'REAL-REF-123', 'transaction_date' => now()->format('Y-m-d H:i:s')];
    }

    public function test_verified_api_receipt_confirms_once_and_activates_booking(): void
    {
        $payment = $this->setupPayment();
        Http::fake(['my.sepay.vn/*' => Http::response(['transactions' => [$this->transfer($payment)]])]);
        $this->actingAs($payment->booking->user)->getJson(route('payments.sepay.status', $payment))->assertOk()->assertJsonPath('status', 'success');
        $this->assertSame('paid', $payment->booking->fresh()->payment_status);
        $points = $payment->booking->user->fresh()->loyalty_points;
        $this->getJson(route('payments.sepay.status', $payment))->assertJsonPath('status', 'success');
        $this->assertSame($points, $payment->booking->user->fresh()->loyalty_points);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_mismatched_account_amount_content_and_outgoing_transfers_do_not_confirm(): void
    {
        $payment = $this->setupPayment();
        $valid = $this->transfer($payment);
        $rows = [];
        foreach ([['account_number' => 'wrong'], ['amount_in' => 1], ['transaction_content' => 'OTHER'], ['amount_out' => 1], ['reference_number' => ''], ['transaction_date' => '2020-01-01 00:00:00']] as $override) {
            $rows[] = array_merge($valid, $override);
        }
        Http::fake(['my.sepay.vn/*' => Http::response(['transactions' => $rows])]);
        $this->actingAs($payment->booking->user)->getJson(route('payments.sepay.status', $payment))->assertOk()->assertJsonPath('status', 'pending');
        $this->assertSame('unpaid', $payment->booking->fresh()->payment_status);
    }

    public function test_api_failure_keeps_payment_pending_and_is_reported_without_faking_success(): void
    {
        $payment = $this->setupPayment();
        Http::fake(['my.sepay.vn/*' => Http::response(['error' => 'Unauthorized'], 401)]);
        $this->actingAs($payment->booking->user)->getJson(route('payments.sepay.status', $payment))->assertOk()->assertJsonPath('status', 'pending')->assertJsonPath('reconciliation', 'unavailable');
        $this->assertSame('unpaid', $payment->booking->fresh()->payment_status);
    }
}
