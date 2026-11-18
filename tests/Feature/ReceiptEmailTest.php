<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PaymentReceiptNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class ReceiptEmailTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    public function test_admin_can_resend_a_paid_receipt_without_changing_the_payment(): void
    {
        Notification::fake();
        config(['mail.default' => 'smtp']);
        $booking = $this->bookingFor();
        $booking->update(['status' => 'confirmed', 'payment_status' => 'paid']);
        $before = $booking->fresh()->getAttributes();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('admin.bookings.receipt-email', $booking))->assertRedirect()->assertSessionHas('success');
        Notification::assertSentTo($booking->user, PaymentReceiptNotification::class);
        $this->assertSame($before, $booking->fresh()->getAttributes());
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_log_mailer_does_not_claim_an_email_was_sent(): void
    {
        Notification::fake();
        config(['mail.default' => 'log']);
        $booking = $this->bookingFor();
        $booking->update(['status' => 'confirmed', 'payment_status' => 'paid']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('admin.bookings.receipt-email', $booking))->assertRedirect()->assertSessionHas('error')->assertSessionMissing('success');
        Notification::assertNothingSent();
    }

    public function test_unpaid_and_refunded_orders_cannot_receive_a_paid_receipt(): void
    {
        Notification::fake();
        $booking = $this->bookingFor();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('admin.bookings.receipt-email', $booking))->assertUnprocessable();
        $booking->update(['status' => 'confirmed', 'payment_status' => 'refunded']);
        $this->post(route('admin.bookings.receipt-email', $booking))->assertUnprocessable();
        Notification::assertNothingSent();
    }

    public function test_customer_and_staff_cannot_resend_receipts(): void
    {
        Notification::fake();
        $booking = $this->bookingFor();
        foreach (['customer', 'staff'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->post(route('admin.bookings.receipt-email', $booking))->assertForbidden();
        }
        Notification::assertNothingSent();
    }
}
