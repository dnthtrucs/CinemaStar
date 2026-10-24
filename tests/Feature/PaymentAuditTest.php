<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class PaymentAuditTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    public function test_simulation_records_success_once_and_links_the_receipt_in_the_journal(): void
    {
        Notification::fake();
        config(['cinema.payment_mode' => 'simulate']);
        $booking = $this->bookingFor();
        $this->actingAs($booking->user)->post(route('payments.store', $booking), ['provider' => 'momo'])->assertRedirect();
        $payment = Payment::firstOrFail();
        $this->post(route('payments.simulate.complete', $payment), ['result' => 'success'])->assertRedirect();
        $this->post(route('payments.simulate.complete', $payment), ['result' => 'success'])->assertUnprocessable();
        $log = ActivityLog::where('action', 'payment.success')->sole();
        $this->assertSame($payment->id, $log->subject_id);
        $this->assertSame(Payment::class, $log->subject_type);
        $this->assertSame($booking->user_id, $log->user_id);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.activity-logs.index', ['group' => 'payment']))->assertOk()
            ->assertSee($booking->code)->assertSee('#payment-'.$payment->id, false);
    }

    public function test_verified_callback_has_one_system_audit_and_rejected_amount_has_none(): void
    {
        Notification::fake();
        config(['cinema.sepay.api_key' => 'key', 'cinema.sepay.account_number' => '123', 'cinema.sepay.bank_code' => 'MBBank']);
        $booking = $this->bookingFor();
        $payment = Payment::create([
            'booking_id' => $booking->id, 'provider' => 'sepay', 'request_id' => 'PAY261025000000ABC123',
            'amount' => 80000, 'status' => 'pending',
        ]);
        $payload = ['accountNumber' => '123', 'transferType' => 'in', 'transferAmount' => 80000,
            'content' => $payment->request_id, 'referenceCode' => 'AUDIT-1'];
        $headers = ['Authorization' => 'Apikey key'];
        $this->postJson(route('payments.sepay.webhook'), array_replace($payload, ['transferAmount' => 70000]), $headers)->assertUnprocessable();
        $this->assertSame(0, ActivityLog::where('action', 'payment.success')->count());
        $this->postJson(route('payments.sepay.webhook'), $payload, $headers)->assertOk();
        $this->postJson(route('payments.sepay.webhook'), $payload, $headers)->assertOk();
        $log = ActivityLog::where('action', 'payment.success')->sole();
        $this->assertNull($log->user_id);
        $this->assertSame($payment->id, $log->subject_id);
        $this->assertSame(80000, $log->properties['amount']);
        $this->assertArrayNotHasKey('payload', $log->properties);
    }
}
