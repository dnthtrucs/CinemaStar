<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class ManualRefundResolutionTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    private function receipt(string $code = 'DUPLICATE_TRANSFER'): Payment
    {
        return Payment::create(['booking_id' => $this->bookingFor()->id, 'provider' => 'sepay',
            'request_id' => Str::random(20), 'transaction_id' => Str::random(20), 'amount' => 80000,
            'status' => 'failed', 'response_code' => $code]);
    }

    public function test_admin_resolves_once_without_changing_the_bank_receipt(): void
    {
        $payment = $this->receipt();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/admin')->assertSee($payment->request_id);
        $this->patch('/admin/manual-refunds/'.$payment->id, ['note' => 'Đã chuyển lại, mã RETURN-123'])->assertRedirect();
        $payment->refresh();
        $this->assertNotNull($payment->refund_resolved_at);
        $this->assertSame($admin->id, $payment->refund_resolved_by);
        $this->assertSame('Đã chuyển lại, mã RETURN-123', $payment->refund_resolution_note);
        $this->assertSame('failed', $payment->status);
        $this->assertSame('DUPLICATE_TRANSFER', $payment->response_code);
        $this->get('/admin')->assertOk()->assertDontSee($payment->request_id);
        $this->patch('/admin/manual-refunds/'.$payment->id, ['note' => 'Lặp'])->assertUnprocessable();
        $this->assertSame(1, ActivityLog::where('action', 'payment.manual_refund_resolved')->where('subject_id', $payment->id)->count());
    }

    public function test_only_unresolved_manual_refunds_may_be_resolved_and_a_note_is_required(): void
    {
        $payment = $this->receipt();
        $normal = $payment->replicate(['request_id', 'transaction_id']);
        $normal->fill(['request_id' => 'NORMAL', 'transaction_id' => 'NORMAL', 'response_code' => '00'])->save();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['', str_repeat('x', 1001)] as $note) {
            $this->patchJson('/admin/manual-refunds/'.$payment->id, ['note' => $note])->assertUnprocessable()->assertJsonValidationErrors('note');
        }
        $this->patch('/admin/manual-refunds/'.$normal->id, ['note' => 'Không hợp lệ'])->assertUnprocessable();
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_staff_and_customers_cannot_read_or_resolve_manual_refunds(): void
    {
        $payment = $this->receipt();
        foreach (['staff', 'customer'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get('/admin/manual-refunds')->assertForbidden();
            $this->patch('/admin/manual-refunds/'.$payment->id, ['note' => 'Không có quyền'])->assertForbidden();
        }
    }

    public function test_list_filters_resolved_receipts_and_paginates_twenty(): void
    {
        $pending = $this->receipt();
        $resolved = $pending->replicate(['request_id', 'transaction_id']);
        $resolved->fill(['request_id' => 'RESOLVED', 'transaction_id' => 'RESOLVED', 'refund_resolved_at' => now(), 'refund_resolution_note' => 'Đối soát xong'])->save();
        for ($i = 0; $i < 21; $i++) {
            $copy = $pending->replicate(['request_id', 'transaction_id']);
            $copy->fill(['request_id' => 'PENDING-'.$i, 'transaction_id' => 'PENDING-'.$i])->save();
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get('/admin/manual-refunds')->assertOk()->assertDontSee('RESOLVED')
            ->assertViewHas('payments', fn ($payments) => $payments->count() === 20 && $payments->total() === 22);
        $this->get('/admin/manual-refunds?status=resolved')->assertOk()->assertSee('RESOLVED')->assertSee('Đối soát xong')->assertDontSee($pending->request_id);
        $this->get('/admin/manual-refunds?status=all')->assertOk()->assertViewHas('payments', fn ($payments) => $payments->total() === 23);
        $this->assertSame(22, Payment::awaitingManualRefund()->count());
    }
}
