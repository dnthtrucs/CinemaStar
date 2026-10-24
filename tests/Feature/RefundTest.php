<?php

namespace Tests\Feature;

use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class RefundTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    public function test_duplicate_request_redirects_with_a_friendly_error(): void
    {
        $booking = $this->bookingFor();
        $booking->update(['status' => 'confirmed', 'payment_status' => 'paid']);
        $this->actingAs($booking->user)->post(route('refunds.store', $booking), ['reason' => 'Đổi lịch'])->assertRedirect();
        $this->actingAs($booking->user)->post(route('refunds.store', $booking), ['reason' => 'Đổi lịch'])->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseCount('refund_requests', 1);
    }

    public function test_checked_in_or_started_bookings_cannot_request_refunds(): void
    {
        $booking = $this->bookingFor();
        $booking->update(['status' => 'confirmed', 'payment_status' => 'paid']);
        $booking->tickets()->update(['status' => 'used', 'checked_in_at' => now()]);
        $this->actingAs($booking->user)->post(route('refunds.store', $booking), ['reason' => 'Đổi lịch'])->assertRedirect()->assertSessionHas('error');
        $booking->tickets()->update(['status' => 'valid', 'checked_in_at' => null]);
        $booking->showtime->update(['starts_at' => now()->subMinute()]);
        $this->post(route('refunds.store', $booking), ['reason' => 'Đổi lịch'])->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseCount('refund_requests', 0);
    }

    public function test_requested_refund_must_be_approved_before_completion(): void
    {
        $booking = $this->bookingFor();
        $booking->update(['status' => 'confirmed', 'payment_status' => 'paid']);
        $refund = RefundRequest::create(['booking_id' => $booking->id, 'requested_by' => $booking->user_id, 'reason' => 'Đổi lịch', 'amount' => 80000]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->patch(route('admin.refunds.update', $refund), ['status' => 'refunded'])->assertUnprocessable();
        $this->assertSame('requested', $refund->fresh()->status);
        $this->assertSame('paid', $booking->fresh()->payment_status);
    }

    public function test_refunded_payments_are_excluded_from_revenue(): void
    {
        $booking = $this->bookingFor();
        $booking->update(['status' => 'cancelled', 'payment_status' => 'refunded']);
        \App\Models\Payment::create(['booking_id' => $booking->id, 'provider' => 'momo', 'request_id' => 'REFUNDED', 'amount' => 80000, 'status' => 'success', 'paid_at' => now()]);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertViewHas('stats', fn ($stats) => (int) $stats['revenue'] === 0 && (int) $stats['today_revenue'] === 0);
        $this->get(route('admin.reports.excel'))->assertOk()->assertDontSee($booking->code);
    }

    public function test_refund_releases_seats_adjusts_points_and_blocks_checkin(): void
    {
        $user = User::factory()->create(['loyalty_points' => 47]);
        $booking = $this->bookingFor($user);
        $booking->forceFill(['status' => 'confirmed', 'payment_status' => 'paid', 'points_used' => 10, 'points_earned' => 7])->save();
        $refund = RefundRequest::create(['booking_id' => $booking->id, 'requested_by' => $user->id, 'reason' => 'Đổi lịch', 'amount' => 70000, 'status' => 'approved']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->patch(route('admin.refunds.update', $refund), ['status' => 'refunded'])->assertRedirect();

        $this->assertSame('refunded', $refund->fresh()->status);
        $this->assertSame('refunded', $booking->fresh()->payment_status);
        $this->assertSame('refunded', $booking->fresh()->refund_status);
        $this->assertNotNull($booking->fresh()->refunded_at);
        $this->assertSame('cancelled', $booking->fresh()->ticket_status);
        $this->assertSame(0, $booking->tickets()->count());
        $this->assertSame(50, (int) $user->fresh()->loyalty_points);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $user->id]);
        $this->actingAs($admin)->patch(route('admin.tickets.update', $booking))->assertUnprocessable();
        $this->actingAs($admin)->patch(route('admin.refunds.update', $refund), ['status' => 'refunded'])->assertUnprocessable();
        $this->assertSame(50, (int) $user->fresh()->loyalty_points);
    }

    public function test_cancelled_paid_booking_cannot_be_checked_in_by_either_qr(): void
    {
        $booking = $this->bookingFor();
        $booking->update(['status' => 'cancelled', 'payment_status' => 'paid']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->patch(route('admin.tickets.update', $booking))->assertUnprocessable();
        $this->actingAs($admin)->post(route('admin.tickets.scan'), ['ticket_token' => $booking->tickets->first()->qr_token])->assertUnprocessable();
    }
}
