<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Cinema;
use App\Models\Payment;
use App\Models\User;
use App\Models\Voucher;
use App\Services\BookingService;
use App\Services\ShowtimeCancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class OperationsUpgradeTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    public function test_staff_cannot_lookup_preview_checkin_or_print_tickets_of_another_cinema(): void
    {
        $b = $this->bookingFor();
        $b->update(['status' => 'confirmed', 'payment_status' => 'paid']);
        $other = Cinema::create(['name' => 'Other', 'slug' => 'other', 'city' => 'Hà Nội', 'location' => 'Other', 'is_active' => true]);
        $staff = User::factory()->create(['role' => 'staff', 'cinema_id' => $other->id]);
        $this->actingAs($staff)->get(route('staff.tickets.index', ['code' => $b->code]))->assertForbidden();
        $this->post(route('staff.tickets.scan'), ['ticket_token' => $b->barcodeValue(), 'intent' => 'preview'])->assertForbidden();
        $this->post(route('staff.tickets.scan'), ['ticket_token' => $b->verificationUrl()])->assertForbidden();
        $this->patch(route('staff.tickets.update', $b))->assertForbidden();
        $this->get(route('bookings.print', $b))->assertForbidden();
        $staff->update(['cinema_id' => null]);
        $this->patch(route('staff.tickets.update', $b))->assertForbidden();
        $this->assertNull($b->tickets()->first()->checked_in_at);
        $staff->update(['cinema_id' => $b->showtime->room->cinema_id]);
        $this->patch(route('staff.tickets.update', $b))->assertRedirect();
        $this->get(route('bookings.print', $b))->assertOk();
    }

    public function test_assignment_requires_cinema_and_audits_before_and_after_without_passwords(): void
    {
        $b = $this->bookingFor();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->put(route('admin.users.update', $b->user), ['role' => 'staff', 'is_active' => 1])->assertSessionHasErrors('cinema_id');
        $this->put(route('admin.users.update', $b->user), ['role' => 'staff', 'is_active' => 1, 'cinema_id' => $b->showtime->room->cinema_id])->assertRedirect()->assertSessionHasNoErrors();
        $log = ActivityLog::where('action', 'user.permissions_updated')->firstOrFail();
        $this->assertSame('customer', $log->properties['before']['role']);
        $this->assertSame('staff', $log->properties['after']['role']);
        $this->assertArrayNotHasKey('password', $log->properties['after']);
    }

    public function test_bulk_cancel_has_preview_releases_holds_and_keeps_payment_records_for_reconciliation(): void
    {
        Mail::fake();
        [$s,$seats] = $this->showtimeWithSeats();
        $pending = app(BookingService::class)->create(User::factory()->create(), $s, [$seats[0]->id]);
        $paid = app(BookingService::class)->create(User::factory()->create(), $s, [$seats[1]->id]);
        $paid->update(['status' => 'confirmed', 'payment_status' => 'paid']);
        $payment = Payment::create(['booking_id' => $paid->id, 'provider' => 'sepay', 'request_id' => 'PAID-CANCEL', 'amount' => $paid->total_price, 'status' => 'success', 'transaction_id' => 'BANK-CANCEL']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.showtimes.cancel-preview'), ['showtime_ids' => [$s->id], 'reason' => 'Bảo trì máy chiếu'])->assertOk()->assertSee('Kiểm tra trước khi hủy suất');
        $this->assertSame('scheduled', $s->fresh()->status);
        $token = session('cancel-preview.token');
        $this->post(route('admin.showtimes.cancel-bulk'), ['token' => $token])->assertRedirect();
        $this->assertSame('cancelled', $s->fresh()->status);
        $this->assertSame('cancelled', $pending->fresh()->status);
        $this->assertSame(0, $pending->tickets()->count());
        $this->assertSame('paid', $paid->fresh()->payment_status);
        $this->assertSame('success', $payment->fresh()->status);
        $this->assertDatabaseCount('notifications', 2);
        $this->get(route('admin.reconciliation.index'))->assertOk()->assertSee('PAID-CANCEL');
        $this->patch(route('admin.tickets.update', $paid))->assertUnprocessable();
        $this->assertSame(0, app(ShowtimeCancellationService::class)->cancel([$s->id], 'Lặp lại yêu cầu'));
        $this->assertDatabaseCount('notifications', 2);
        $this->post(route('admin.showtimes.cancel-bulk'), ['token' => $token])->assertUnprocessable();
    }

    public function test_cancel_rejects_entire_batch_if_one_showtime_has_checked_in_ticket(): void
    {
        $b = $this->bookingFor();
        $b->tickets()->update(['status' => 'used', 'checked_in_at' => now()]);
        $second = $b->showtime->replicate();
        $second->starts_at = now()->addDays(2);
        $second->ends_at = now()->addDays(2)->addHours(2);
        $second->save();
        try {
            app(ShowtimeCancellationService::class)->cancel([$b->showtime_id, $second->id], 'Phòng gặp sự cố');
            $this->fail('Expected rejection');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertSame('scheduled', $second->fresh()->status);
        $this->assertSame('scheduled', $b->showtime->fresh()->status);
    }

    public function test_reconciliation_resolution_is_admin_only_idempotent_and_does_not_forge_payment_status(): void
    {
        $b = $this->bookingFor();
        $payment = Payment::create(['booking_id' => $b->id, 'provider' => 'sepay', 'request_id' => 'LATE', 'amount' => 80000, 'status' => 'failed', 'response_code' => 'PAID_AFTER_EXPIRY', 'transaction_id' => 'BANK-LATE']);
        $data = ['bank_reference' => 'BANK-RESULT-123', 'note' => 'Đã kiểm tra sao kê và liên hệ khách xử lý.'];
        $this->actingAs($b->user)->get(route('admin.reconciliation.index'))->assertForbidden();
        $this->post(route('admin.reconciliation.resolve', $payment), $data)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('admin.reconciliation.resolve', $payment), $data)->assertRedirect();
        $this->post(route('admin.reconciliation.resolve', $payment), $data)->assertUnprocessable();
        $this->assertDatabaseCount('payment_reconciliations', 1);
        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('unpaid', $b->fresh()->payment_status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'payment.reconciliation_resolved', 'subject_id' => $payment->id]);
    }

    public function test_voucher_suggestions_rank_savings_and_explain_minimum_and_previous_use(): void
    {
        $b = $this->bookingFor();
        foreach ([['BEST', 30000, 100000], ['SMALL', 10000, 100000], ['HIGHMIN', 20000, 500000]] as [$code,$value,$min]) {
            Voucher::create(['code' => $code, 'name' => $code, 'type' => 'fixed', 'value' => $value, 'min_order' => $min]);
        }
        $this->actingAs($b->user)->getJson(route('vouchers.suggestions', ['subtotal' => 200000]))->assertOk()->assertJsonPath('vouchers.0.code', 'BEST')->assertJsonPath('vouchers.0.discount', 30000)->assertJsonPath('vouchers.2.usable', false);
        $b->update(['voucher_id' => Voucher::where('code', 'BEST')->value('id')]);
        $this->getJson(route('vouchers.suggestions', ['subtotal' => 200000]))->assertOk()->assertJsonPath('vouchers.0.code', 'SMALL');
        $this->getJson(route('vouchers.suggestions', ['subtotal' => -1]))->assertUnprocessable();
    }

    public function test_catalog_audit_records_before_after_and_is_visible(): void
    {
        $b = $this->bookingFor();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $b->showtime->movie->update(['title' => 'Tên phim cập nhật']);
        $log = ActivityLog::where('action', 'catalog.updated')->firstOrFail();
        $this->assertSame('Phim Test', $log->properties['before']['title']);
        $this->assertSame('Tên phim cập nhật', $log->properties['after']['title']);
        $this->get(route('admin.activity-logs.index', ['group' => 'catalog']))->assertOk()->assertSee('Tên phim cập nhật');
    }
}
