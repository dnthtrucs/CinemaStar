<?php

namespace Tests\Feature;

use App\Models\Concession;
use App\Models\User;
use App\Notifications\PaymentReceiptNotification;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class ConcessionNameSnapshotTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    public function test_changing_catalogue_name_and_price_keeps_the_original_order_and_ticket_contents(): void
    {
        [$showtime, $seats] = $this->showtimeWithSeats();
        $customer = User::factory()->create();
        $combo = Concession::create(['name' => 'Combo vé cũ', 'price' => 50000, 'is_active' => true]);
        $booking = app(BookingService::class)->create($customer, $showtime, [$seats[0]->id], concessions: [$combo->id => 2]);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->put(route('admin.concessions.update', $combo), [
            'name' => 'Combo tên mới', 'price' => 99000, 'is_active' => true,
        ])->assertRedirect();

        $this->assertSame('Combo tên mới', $combo->fresh()->name);
        $this->assertDatabaseHas('booking_concessions', [
            'booking_id' => $booking->id, 'concession_id' => $combo->id,
            'name' => 'Combo vé cũ', 'quantity' => 2, 'unit_price' => 50000,
        ]);
        $booking = $booking->fresh();
        $this->assertSame('Combo vé cũ', $booking->concessions->first()->pivot->name);
        $this->assertSame('Combo vé cũ', $combo->bookings()->first()->pivot->name);
        $this->actingAs($customer)->get(route('bookings.show', $booking))->assertOk()
            ->assertSeeText('Combo vé cũ')->assertSeeText('50.000')->assertDontSeeText('Combo tên mới');
        $this->get(route('payments.show', $booking))->assertOk()
            ->assertSeeText('Combo vé cũ')->assertDontSeeText('Combo tên mới');

        $booking->update(['status' => 'confirmed', 'payment_status' => 'paid']);
        $this->get(route('bookings.print', $booking))->assertOk()->assertSeeText('Combo vé cũ')
            ->assertSeeText('50.000')->assertSeeText('100.000')->assertDontSeeText('Combo tên mới');
        $this->get($booking->verificationUrl())->assertOk()->assertSeeText('Combo vé cũ')->assertDontSeeText('Combo tên mới');
        $this->actingAs($admin)->get(route('admin.tickets.index', ['code' => $booking->code]))->assertOk()
            ->assertSeeText('Combo vé cũ')->assertSeeText('50.000')->assertDontSeeText('Combo tên mới');
        $this->get(route('admin.bookings.show', $booking))->assertOk()
            ->assertSeeText('Combo vé cũ')->assertDontSeeText('Combo tên mới');
        $mail = (new PaymentReceiptNotification($booking->fresh()))->toMail($customer)->render();
        $this->assertStringContainsString('Combo vé cũ', $mail);
        $this->assertStringContainsString('50.000', $mail);
        $this->assertStringContainsString('100.000', $mail);
        $this->assertStringNotContainsString('Combo tên mới', $mail);
        $this->assertStringNotContainsString('99.000', $mail);
    }
}
