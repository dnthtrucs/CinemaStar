<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BookingExchangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class ExchangeLifecycleTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    private function fixture(): array
    {
        $booking = $this->bookingFor();
        $booking->update(['status' => 'confirmed', 'payment_status' => 'paid']);
        $target = $booking->showtime->replicate();
        $target->starts_at = now()->addDays(2);
        $target->ends_at = now()->addDays(2)->addHours(2);
        $target->save();
        $exchange = app(BookingExchangeService::class)->request($booking, $target, $booking->tickets->pluck('seat_id')->all(), 'Yêu cầu đổi lịch cá nhân');

        return [$booking, $target, $exchange];
    }

    public function test_owner_can_withdraw_without_changing_tickets_or_money_and_resubmit(): void
    {
        [$booking,$target,$exchange] = $this->fixture();
        $before = $booking->fresh()->getAttributes();
        $tickets = $booking->tickets()->get()->toArray();
        $this->actingAs($booking->user)->post(route('exchanges.withdraw', $exchange))->assertRedirect(route('exchanges.create', $booking));
        $this->assertSame('withdrawn', $exchange->fresh()->status);
        $this->assertSame($before, $booking->fresh()->getAttributes());
        $this->assertSame($tickets, $booking->tickets()->get()->toArray());
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('activity_logs', ['action' => 'booking.exchange.withdrawn', 'subject_id' => $booking->id]);
        $new = app(BookingExchangeService::class)->request($booking, $target, $booking->tickets->pluck('seat_id')->all(), 'Yêu cầu mới');
        $this->assertNotEquals($exchange->id, $new->id);
        $this->assertSame('pending', $new->fresh()->status);
    }

    public function test_other_customer_and_admin_cannot_withdraw_an_owners_request(): void
    {
        [$booking,$target,$exchange] = $this->fixture();
        foreach (['customer', 'admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->post(route('exchanges.withdraw', $exchange))->assertForbidden();
        }
        $this->assertSame('pending', $exchange->fresh()->status);
    }

    public function test_withdrawn_request_cannot_be_withdrawn_again_or_approved(): void
    {
        [$booking,$target,$exchange] = $this->fixture();
        $svc = app(BookingExchangeService::class);
        $svc->withdraw($exchange, $booking->user);
        $this->actingAs($booking->user)->postJson(route('exchanges.withdraw', $exchange))->assertUnprocessable();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->patchJson(route('admin.exchanges.resolve', $exchange), ['decision' => 'approve', 'note' => 'Xác nhận kiểm tra', 'verified' => 1])->assertUnprocessable();
        $this->assertSame('withdrawn', $exchange->fresh()->status);
        $this->assertEquals($booking->showtime_id, $booking->fresh()->showtime_id);
    }

    public function test_approved_request_cannot_be_withdrawn(): void
    {
        [$booking,$target,$exchange] = $this->fixture();
        app(BookingExchangeService::class)->resolve($exchange, User::factory()->create(['role' => 'admin']), true, 'Đã kiểm tra điều kiện', null);
        $this->actingAs($booking->user)->postJson(route('exchanges.withdraw', $exchange))->assertUnprocessable();
        $this->assertSame('approved', $exchange->fresh()->status);
    }

    public function test_history_stays_visible_after_cutoff_but_no_new_form_is_offered(): void
    {
        [$booking,$target,$exchange] = $this->fixture();
        $booking->showtime->update(['starts_at' => now()->addMinutes(30)]);
        $this->actingAs($booking->user)->get(route('exchanges.create', [$booking, 'target' => $target->id]))->assertOk()->assertSee('không đủ điều kiện')->assertSee('Rút yêu cầu, giữ vé hiện tại')->assertDontSee('id="exchangeForm"', false);
        $this->get(route('bookings.show', $booking))->assertOk()->assertSee('Đổi suất / theo dõi yêu cầu');
    }

    public function test_admin_filters_and_quote_snapshots_are_displayed(): void
    {
        [$booking,$target,$exchange] = $this->fixture();
        $svc = app(BookingExchangeService::class);
        $svc->withdraw($exchange, $booking->user);
        $pending = $svc->request($booking, $target, $booking->tickets->pluck('seat_id')->all(), 'Yêu cầu đang chờ');
        $this->assertSame('A1', $pending->snapshot['new_seats'][0]['seat']);
        $this->assertNotEmpty($pending->snapshot['from_time']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.exchanges.index'))->assertOk()->assertSee('Yêu cầu #'.$pending->id)->assertDontSee('Yêu cầu #'.$exchange->id)->assertSee('Ghế cũ: A1')->assertSee('Tổng cũ');
        $this->get(route('admin.exchanges.index', ['status' => 'withdrawn']))->assertOk()->assertSee('Yêu cầu #'.$exchange->id)->assertDontSee('Yêu cầu #'.$pending->id);
        $this->get(route('admin.exchanges.index', ['status' => 'all']))->assertOk()->assertSee('Yêu cầu #'.$exchange->id)->assertSee('Yêu cầu #'.$pending->id);
        $this->getJson(route('admin.exchanges.index', ['status' => 'nonsense']))->assertUnprocessable();
    }

    public function test_admin_can_reject_without_claiming_a_bank_transfer_was_verified(): void
    {
        [$booking, $target, $exchange] = $this->fixture();
        $old = $booking->barcodeValue();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patch(route('admin.exchanges.resolve', $exchange), ['decision' => 'reject', 'note' => 'Khách chưa đủ điều kiện đổi'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('rejected', $exchange->fresh()->status);
        $this->assertSame($old, $booking->fresh()->barcodeValue());
        $this->assertDatabaseCount('payments', 0);
    }
}
