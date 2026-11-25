<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BookingExchangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class ExchangeNoticeTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    public function test_old_and_target_screenings_require_at_least_twelve_hours_including_exact_boundary(): void
    {
        $this->travelTo(now()->startOfSecond());
        $booking = $this->bookingFor();
        $booking->update(['status' => 'confirmed', 'payment_status' => 'paid']);
        $booking->showtime->update(['starts_at' => now()->addHours(12)]);
        $svc = app(BookingExchangeService::class);
        $this->assertTrue($svc->eligible($booking));
        $target = $booking->showtime->replicate();
        $target->starts_at = now()->addHours(12);
        $target->ends_at = now()->addHours(14);
        $target->save();
        $ids = $booking->tickets->pluck('seat_id')->all();
        $this->assertSame(0, $svc->quote($booking, $target, $ids)['difference']);
        $booking->showtime->update(['starts_at' => now()->addHours(12)->subSecond()]);
        $this->assertFalse($svc->eligible($booking));
        $booking->showtime->update(['starts_at' => now()->addDay()]);
        $target->update(['starts_at' => now()->addHours(12)->subSecond()]);
        $this->actingAs($booking->user)->get(route('exchanges.create', $booking))->assertOk()->assertViewHas('targets', fn ($items) => ! $items->contains('id', $target->id));
        $this->expectException(ValidationException::class);
        $svc->quote($booking, $target, $ids);
    }

    public function test_approval_inside_final_twelve_hours_preserves_original_ticket_and_payment(): void
    {
        $this->travelTo(now()->startOfSecond());
        $booking = $this->bookingFor();
        $booking->update(['status' => 'confirmed', 'payment_status' => 'paid']);
        $target = $booking->showtime->replicate();
        $target->starts_at = now()->addDays(2);
        $target->ends_at = now()->addDays(2)->addHours(2);
        $target->save();
        $svc = app(BookingExchangeService::class);
        $exchange = $svc->request($booking, $target, $booking->tickets->pluck('seat_id')->all(), 'Đổi lịch cá nhân');
        $old = $booking->barcodeValue();
        $this->travel(13)->hours();
        try {
            $svc->resolve($exchange, User::factory()->create(['role' => 'admin']), true, 'Đã kiểm tra điều kiện', null);
            $this->fail('Late approval accepted');
        } catch (ValidationException) {
        }
        $this->assertSame('pending', $exchange->fresh()->status);
        $this->assertSame($old, $booking->fresh()->barcodeValue());
        $this->assertDatabaseCount('payments', 0);
    }
}
