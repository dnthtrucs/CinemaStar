<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PaymentReceiptNotification;
use App\Services\BookingExchangeService;
use App\Services\TicketBarcode;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class ExchangeReceiptTest extends TestCase
{
    use CreatesCinemaBookings, DatabaseMigrations;

    private function fixture(): array
    {
        $b = $this->bookingFor();
        $b->update(['status' => 'confirmed', 'payment_status' => 'paid']);
        $target = $b->showtime->replicate();
        $target->starts_at = now()->addDays(2);
        $target->ends_at = now()->addDays(2)->addHours(2);
        $target->save();
        $svc = app(BookingExchangeService::class);
        $ex = $svc->request($b, $target, $b->tickets->pluck('seat_id')->all(), 'Đổi lịch');

        return [$b, $target, $ex, User::factory()->create(['role' => 'admin'])];
    }

    public function test_approval_sends_exactly_one_new_receipt_after_commit_with_updated_code_time_and_barcode(): void
    {
        Notification::fake();
        config(['mail.default' => 'smtp']);
        [$b,$target,$ex,$admin] = $this->fixture();
        $old = $b->code;
        $svc = app(BookingExchangeService::class);
        $svc->resolve($ex, $admin, true, 'Đã kiểm tra điều kiện', null);
        Notification::assertSentTo($b->user, PaymentReceiptNotification::class, function ($notification) use ($b, $old, $target) {
            $mail = $notification->toMail($b->user);
            $text = view('emails.payment-receipt-text', $mail->viewData)->render();
            $this->assertSame($old, $notification->previousCode);
            $this->assertSame($b->fresh()->code, $notification->booking->code);
            $this->assertStringContainsString('Vé mới sau đổi suất', $mail->subject);
            $this->assertStringContainsString($target->starts_at->format('H:i d/m/Y'), $text);
            $this->assertStringContainsString('Mã vé cũ đã hết hiệu lực', $text);
            $this->assertSame(app(TicketBarcode::class)->png($b->fresh()), $mail->viewData['barcodePng']);

            return true;
        });
        try {
            $svc->resolve($ex, $admin, true, 'Duyệt lặp', null);
        } catch (ValidationException) {
        }
        Notification::assertSentToTimes($b->user, PaymentReceiptNotification::class, 1);
    }

    public function test_mail_failure_does_not_undo_exchange_and_is_recorded_for_resending(): void
    {
        config(['mail.default' => 'smtp']);
        [$b,$target,$ex,$admin] = $this->fixture();
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('Email transport unavailable in test'));
        app(BookingExchangeService::class)->resolve($ex, $admin, true, 'Đã kiểm tra điều kiện', null);
        $this->assertSame('approved', $ex->fresh()->status);
        $this->assertSame($target->id, $b->fresh()->showtime_id);
        $this->assertDatabaseHas('activity_logs', ['action' => 'booking.exchange.email_failed', 'subject_id' => $b->id]);
    }

    public function test_rejected_requests_do_not_send_new_tickets(): void
    {
        Notification::fake();
        config(['mail.default' => 'smtp']);
        [$b,$target,$ex,$admin] = $this->fixture();
        app(BookingExchangeService::class)->resolve($ex, $admin, false, 'Không đủ điều kiện', null);
        Notification::assertNothingSent();
    }

    public function test_old_barcode_returns_friendly_error_on_scanner_page_without_checking_in_new_ticket(): void
    {
        Notification::fake();
        config(['mail.default' => 'smtp']);
        [$b,$target,$ex,$admin] = $this->fixture();
        $old = $b->barcodeValue();
        app(BookingExchangeService::class)->resolve($ex, $admin, true, 'Đã kiểm tra điều kiện', null);
        $this->actingAs(User::factory()->create(['role' => 'staff']))->post(route('staff.tickets.scan'), ['ticket_token' => $old, 'intent' => 'preview'])->assertRedirect(route('staff.tickets.index'))->assertSessionHas('error');
        $this->get(route('staff.tickets.index'))->assertOk()->assertSee('Mã vé không hợp lệ')->assertDontSee('Oops!');
        $this->assertDatabaseCount('ticket_checkins', 0);
        $this->postJson(route('staff.tickets.scan'), ['ticket_token' => $old, 'intent' => 'preview'])->assertUnprocessable();
    }

    public function test_manual_resend_after_exchange_uses_exchange_receipt_context(): void
    {
        Notification::fake();
        config(['mail.default' => 'smtp']);
        [$b,$target,$ex,$admin] = $this->fixture();
        $old = $b->code;
        app(BookingExchangeService::class)->resolve($ex, $admin, true, 'Đã kiểm tra điều kiện', null);
        Notification::fake();
        $this->actingAs($admin)->get(route('admin.bookings.index', ['q' => $old]))->assertOk()->assertSee($b->fresh()->code);
        $this->get(route('admin.bookings.show', $b))->assertOk()->assertSee($old);
        $this->actingAs($admin)->post(route('admin.bookings.receipt-email', $b))->assertRedirect();
        Notification::assertSentTo($b->user, PaymentReceiptNotification::class, fn ($notice) => $notice->previousCode === $old && $notice->booking->code === $b->fresh()->code);
    }
}
