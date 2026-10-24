<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    private function entry(string $action, string $description, ?User $actor = null, array $attributes = []): ActivityLog
    {
        return ActivityLog::forceCreate(array_merge([
            'user_id' => $actor?->id,
            'action' => $action,
            'description' => $description,
        ], $attributes));
    }

    public function test_admin_reads_the_log_and_the_page_has_no_write_endpoints(): void
    {
        $actor = User::factory()->create(['name' => 'Người đối soát', 'email' => 'audit@example.test']);
        $log = $this->entry('payment.manual_refund_resolved', 'Đã đối soát biên lai', $actor);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get('/admin/activity-logs')->assertOk()
            ->assertSeeText('Nhật ký hoạt động')->assertSeeText($actor->name)->assertSeeText($actor->email)
            ->assertSeeText($log->action)->assertSeeText($log->description);
        foreach (['post', 'patch', 'delete'] as $method) {
            $this->{$method}('/admin/activity-logs', ['description' => 'Không được ghi đè'])->assertStatus(405);
        }
        $this->assertDatabaseCount('activity_logs', 1);
        $this->assertSame('Đã đối soát biên lai', $log->fresh()->description);
    }

    public function test_staff_customers_and_guests_cannot_read_logs(): void
    {
        $this->get('/admin/activity-logs')->assertRedirect(route('login'));
        foreach (['staff', 'customer'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/admin/activity-logs')->assertForbidden();
        }
    }

    public function test_group_and_actor_filters_match_the_prefix_and_name_or_email_together(): void
    {
        $actor = User::factory()->create(['name' => 'Nguyễn Minh', 'email' => 'cashier@example.test']);
        $other = User::factory()->create(['name' => 'Người khác', 'email' => 'other@example.test']);
        $this->entry('payment.success', 'MATCH-PAYMENT', $actor);
        $this->entry('refund.refunded', 'REFUND-ACTOR', $actor);
        $this->entry('paymentish.success', 'WRONG-PREFIX', $actor);
        $this->entry('payment.success', 'OTHER-ACTOR', $other);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        foreach (['Minh', 'cashier@'] as $search) {
            $this->get('/admin/activity-logs?'.http_build_query(['group' => 'payment', 'actor' => $search]))
                ->assertOk()->assertSeeText('MATCH-PAYMENT')->assertDontSeeText('REFUND-ACTOR')
                ->assertDontSeeText('WRONG-PREFIX')->assertDontSeeText('OTHER-ACTOR')
                ->assertViewHas('logs', fn ($logs) => $logs->total() === 1);
        }
        foreach (['refund', 'ticket', 'booking', 'review'] as $group) {
            $this->entry($group.'.test', 'GROUP-'.$group, $other);
            $this->get('/admin/activity-logs?group='.$group)->assertOk()->assertSeeText('GROUP-'.$group)
                ->assertDontSeeText('MATCH-PAYMENT')->assertDontSeeText('WRONG-PREFIX');
        }
    }

    public function test_date_filters_include_the_whole_vietnamese_day_even_with_utc_storage(): void
    {
        config(['app.timezone' => 'UTC']);
        $this->entry('payment.success', 'BEFORE-DAY', attributes: ['created_at' => '2026-10-19 16:59:59']);
        $this->entry('payment.success', 'FIRST-SECOND', attributes: ['created_at' => '2026-10-19 17:00:00']);
        $this->entry('payment.success', 'LAST-SECOND', attributes: ['created_at' => '2026-10-20 16:59:59']);
        $this->entry('payment.success', 'AFTER-DAY', attributes: ['created_at' => '2026-10-20 17:00:00']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get('/admin/activity-logs?from=2026-10-20&to=2026-10-20')->assertOk()
            ->assertSeeText('FIRST-SECOND')->assertSeeText('LAST-SECOND')
            ->assertDontSeeText('BEFORE-DAY')->assertDontSeeText('AFTER-DAY')
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 2);
        $this->get('/admin/activity-logs?to=2026-10-20')->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 3);
    }

    public function test_newest_entries_are_paginated_thirty_at_a_time_and_keep_filters(): void
    {
        $actor = User::factory()->create(['name' => 'Lọc phân trang']);
        $entries = collect();
        for ($i = 1; $i <= 32; $i++) {
            $entries->push($this->entry('payment.success', 'ENTRY-'.$i, $actor, ['created_at' => '2026-10-20 12:00:00']));
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $query = ['group' => 'payment', 'actor' => 'Lọc phân trang'];

        $this->get('/admin/activity-logs?'.http_build_query($query))->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->count() === 30 && $logs->total() === 32
                && $logs->first()->id === $entries->last()->id
                && str_contains($logs->nextPageUrl(), 'group=payment')
                && str_contains($logs->nextPageUrl(), 'actor='));
        $this->get('/admin/activity-logs?'.http_build_query($query + ['page' => 2]))->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->pluck('id')->all() === [$entries[1]->id, $entries[0]->id]);
    }

    public function test_related_booking_and_payment_links_work_for_all_existing_subjects_and_missing_subjects_are_safe(): void
    {
        $booking = $this->bookingFor();
        $payment = Payment::create(['booking_id' => $booking->id, 'provider' => 'sepay', 'request_id' => 'AUDIT-PAYMENT', 'amount' => 80000, 'status' => 'pending']);
        $refund = RefundRequest::create(['booking_id' => $booking->id, 'requested_by' => $booking->user_id, 'status' => 'requested', 'reason' => 'Kiểm tra nhật ký', 'amount' => 80000]);
        foreach ([$booking, $payment, $booking->tickets->first(), $refund] as $subject) {
            $this->entry('booking.test', 'SUBJECT-'.class_basename($subject), attributes: [
                'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->id,
            ]);
        }
        $this->entry('payment.test', '<script>alert("audit")</script>', attributes: ['subject_type' => Payment::class, 'subject_id' => 999999]);
        $this->entry('booking.test', 'UNKNOWN-TYPE', attributes: ['subject_type' => 'Legacy\\MissingClass', 'subject_id' => 999999]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get('/admin/activity-logs')->assertOk()
            ->assertSee(route('admin.bookings.show', $booking), false)
            ->assertSee(route('admin.bookings.show', $booking).'#payment-'.$payment->id, false)
            ->assertSeeText($payment->request_id)->assertSeeText($booking->code)
            ->assertSeeText('Hệ thống / tài khoản đã xóa')->assertSeeText('UNKNOWN-TYPE')
            ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert("audit")</script>', false);
        $this->get(route('admin.bookings.show', $booking))->assertOk()->assertSee('id="payment-'.$payment->id.'"', false);
    }

    public function test_invalid_filter_values_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson('/admin/activity-logs?group=payment%25&from=invalid&actor='.str_repeat('x', 256))
            ->assertUnprocessable()->assertJsonValidationErrors(['group', 'from', 'actor']);
        $this->getJson('/admin/activity-logs?from=2026-10-21&to=2026-10-20')
            ->assertUnprocessable()->assertJsonValidationErrors('to');
    }
}
