<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Concession;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\Seat;
use App\Models\User;
use App\Models\Voucher;
use App\Notifications\PaymentReceiptNotification;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class ConcessionTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    private function combo(array $attributes = []): Concession
    {
        return Concession::create($attributes + ['name' => 'Combo bắp đôi', 'price' => 50000, 'is_active' => true, 'sort_order' => 0]);
    }

    public function test_vip_couple_seats_and_combos_are_priced_by_the_server(): void
    {
        [$showtime, $seats] = $this->showtimeWithSeats();
        foreach ([1, 2] as $number) {
            $pair[] = Seat::create(['room_id' => $showtime->room_id, 'row' => 'H', 'number' => $number, 'type' => 'couple', 'price_surcharge' => 45000, 'is_active' => true]);
        }
        $combo = $this->combo();
        $this->actingAs(User::factory()->create())->post(route('bookings.store', $showtime), [
            'seats' => [$seats[1]->id, $pair[0]->id], 'concessions' => [$combo->id => 2], 'subtotal' => 1,
        ])->assertRedirect();
        $booking = Booking::firstOrFail();
        $this->assertSame(3, $booking->quantity);
        $this->assertSame(100000, (int) $booking->concessions_total);
        $this->assertSame(460000, (int) $booking->subtotal);
        $this->assertSame(460000, (int) $booking->total_price);
        $this->assertDatabaseHas('booking_concessions', ['booking_id' => $booking->id, 'quantity' => 2, 'unit_price' => 50000]);
    }

    public function test_percentage_voucher_and_minimum_order_use_seats_plus_combos(): void
    {
        [$showtime, $seats] = $this->showtimeWithSeats();
        $combo = $this->combo(['price' => 20000]);
        Voucher::create(['code' => 'ALL10', 'name' => 'Toàn đơn', 'type' => 'percent', 'value' => 10, 'min_order' => 100000]);
        $booking = app(BookingService::class)->create(User::factory()->create(), $showtime, [$seats[0]->id], 'ALL10', [$combo->id => 1]);
        $this->assertSame(100000, (int) $booking->subtotal);
        $this->assertSame(10000, (int) $booking->discount);
        $this->assertSame(90000, (int) $booking->total_price);
    }

    public function test_inactive_unknown_or_invalid_combo_quantities_are_rejected(): void
    {
        [$showtime, $seats] = $this->showtimeWithSeats();
        $combo = $this->combo(['is_active' => false]);
        $this->actingAs(User::factory()->create());
        foreach ([[$combo->id => 1], [999999 => 1], [$combo->id => -1], [$combo->id => 11], [1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1, 6 => 1]] as $selection) {
            $this->postJson(route('bookings.store', $showtime), ['seats' => [$seats[0]->id], 'concessions' => $selection])->assertUnprocessable();
        }
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_admin_price_change_preserves_snapshot_and_used_combo_cannot_be_deleted(): void
    {
        [$showtime, $seats] = $this->showtimeWithSeats();
        $combo = $this->combo();
        $booking = app(BookingService::class)->create(User::factory()->create(), $showtime, [$seats[0]->id], concessions: [$combo->id => 2]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->put(route('admin.concessions.update', $combo), ['name' => $combo->name, 'price' => 99000, 'is_active' => true, 'sort_order' => 1])->assertRedirect();
        $this->assertSame(99000, (int) $combo->fresh()->price);
        $this->assertSame(50000, (int) $booking->concessions()->first()->pivot->unit_price);
        $this->assertSame(180000, (int) $booking->fresh()->total_price);
        $this->delete(route('admin.concessions.destroy', $combo))->assertRedirect()->assertSessionHas('error');
        $this->assertNotNull($combo->fresh());
        $this->put(route('admin.concessions.update', $combo), ['name' => $combo->name, 'price' => 99000, 'is_active' => false])->assertRedirect();
        $this->assertFalse($combo->fresh()->is_active);
    }

    public function test_admin_crud_upload_and_customer_authorization(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role' => 'customer']))->get('/admin/concessions')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'staff']))->get('/admin/concessions')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/admin/concessions', [
            'name' => 'Combo thử', 'price' => 35000, 'is_active' => true, 'image' => UploadedFile::fake()->image('combo.jpg'),
        ])->assertRedirect();
        $combo = Concession::firstOrFail();
        Storage::disk('public')->assertExists($combo->image_path);
        $this->get('/admin/concessions')->assertOk()->assertSee('Combo thử');
        $this->get(route('admin.concessions.edit', $combo))->assertOk();
        $this->delete(route('admin.concessions.destroy', $combo))->assertRedirect();
        $this->assertNull($combo->fresh());
        Storage::disk('public')->assertMissing($combo->image_path);
    }

    public function test_award_redemption_and_cash_refund_include_combo_value(): void
    {
        config(['cinema.sepay.api_key' => 'key', 'cinema.sepay.account_number' => '123', 'cinema.sepay.bank_code' => 'MBBank']);
        [$showtime, $seats] = $this->showtimeWithSeats();
        $user = User::factory()->create(['loyalty_points' => 10]);
        $combo = $this->combo();
        $booking = app(BookingService::class)->create($user, $showtime, [$seats[0]->id], concessions: [$combo->id => 1]);
        $this->actingAs($user)->post(route('payments.store', $booking), ['provider' => 'sepay', 'points_to_use' => 10])->assertRedirect();
        $payment = Payment::firstOrFail();
        $this->assertSame(120000, (int) $payment->amount);
        $payload = ['accountNumber' => '123', 'transferType' => 'in', 'transferAmount' => 120000, 'content' => $payment->request_id, 'referenceCode' => 'COMBO-PAID'];
        $this->postJson(route('payments.sepay.webhook'), $payload, ['Authorization' => 'Apikey key'])->assertOk();
        $this->assertSame(12, $booking->fresh()->points_earned);
        $this->assertSame(12, (int) $user->fresh()->loyalty_points);
        $this->post(route('refunds.store', $booking), ['reason' => 'Đổi lịch'])->assertRedirect();
        $refund = RefundRequest::firstOrFail();
        $this->assertSame(120000, (int) $refund->amount);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->patch(route('admin.refunds.update', $refund), ['status' => 'approved'])->assertRedirect();
        $this->patch(route('admin.refunds.update', $refund), ['status' => 'refunded'])->assertRedirect();
        $this->assertSame(10, (int) $user->fresh()->loyalty_points);
        $this->assertSame(0, $user->fresh()->lifetimePoints());
        $this->assertDatabaseCount('booking_concessions', 1);
    }

    public function test_cancellation_and_expiry_keep_combo_history_and_restore_seats_and_points(): void
    {
        config(['cinema.sepay.api_key' => 'key', 'cinema.sepay.account_number' => '123', 'cinema.sepay.bank_code' => 'MBBank']);
        [$showtime, $seats] = $this->showtimeWithSeats();
        $user = User::factory()->create(['loyalty_points' => 10]);
        $combo = $this->combo();
        $service = app(BookingService::class);
        foreach (['cancel', 'expire'] as $action) {
            $booking = $service->create($user, $showtime, [$seats[0]->id], concessions: [$combo->id => 1]);
            $this->actingAs($user)->post(route('payments.store', $booking), ['provider' => 'sepay', 'points_to_use' => 5])->assertRedirect();
            if ($action === 'cancel') {
                $service->cancel($booking);
            } else {
                $booking->update(['expires_at' => now()->subMinute()]);
                $service->expirePendingBooking($booking->id);
            }
            $this->assertSame(10, (int) $user->fresh()->loyalty_points);
            $this->assertSame(0, $booking->tickets()->count());
            $this->assertSame(130000, (int) $booking->fresh()->total_price);
            $this->assertSame(1, $booking->concessions()->count());
        }
    }

    public function test_combos_appear_on_every_ticket_payment_email_print_and_checkin_surface(): void
    {
        [$showtime, $seats] = $this->showtimeWithSeats();
        $combo = $this->combo();
        $booking = app(BookingService::class)->create(User::factory()->create(), $showtime, [$seats[0]->id], concessions: [$combo->id => 2]);
        $this->actingAs($booking->user)->get(route('bookings.show', $booking))->assertOk()->assertSee($combo->name);
        $this->get(route('payments.show', $booking))->assertOk()->assertSee($combo->name);
        $booking->update(['status' => 'confirmed', 'payment_status' => 'paid']);
        $this->get(route('bookings.print', $booking))->assertOk()->assertSee($combo->name)->assertSee('100.000');
        $this->get($booking->verificationUrl())->assertOk()->assertSee($combo->name);
        $message = (new PaymentReceiptNotification($booking))->toMail($booking->user);
        $this->assertStringContainsString($combo->name, $message->render());
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.bookings.show', $booking))->assertOk()->assertSee($combo->name);
        $this->get(route('admin.tickets.index', ['code' => $booking->code]))->assertOk()->assertSee($combo->name);
    }

    public function test_seat_page_offers_combos_and_keeps_every_complete_couple_pair_available(): void
    {
        [$showtime] = $this->showtimeWithSeats();
        $combo = $this->combo();
        foreach ([1, 2, 3, 4] as $number) {
            $pair[] = Seat::create(['room_id' => $showtime->room_id, 'row' => 'H', 'number' => $number, 'type' => 'couple', 'price_surcharge' => 45000, 'is_active' => true]);
        }
        $response = $this->get(route('showtimes.show', $showtime))->assertOk()->assertSee($combo->name)->assertSee('concessions['.$combo->id.']', false);
        $html = new \DOMDocument;
        @$html->loadHTML($response->getContent());
        $input = (new \DOMXPath($html))->query('//input[@name="seats[]"][@value="'.$pair[2]->id.'"]')->item(0);
        $this->assertNotNull($input);
        $this->assertFalse($input->hasAttribute('disabled'));
    }
}
