<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class ScanPreviewTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    public function test_scanned_barcode_opens_order_without_checking_in_until_staff_confirms(): void
    {
        $b = $this->bookingFor();
        $b->update(['status' => 'confirmed', 'payment_status' => 'paid']);
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)->post(route('staff.tickets.scan'), ['ticket_token' => $b->barcodeValue(), 'intent' => 'preview'])->assertRedirect(route('staff.tickets.index', ['code' => $b->code]));
        $this->assertDatabaseMissing('tickets', ['booking_id' => $b->id, 'status' => 'used']);
        $this->assertDatabaseCount('ticket_checkins', 0);
        $this->get(route('staff.tickets.index', ['code' => $b->code]))->assertOk()->assertSee($b->code)->assertSee('Xác nhận check-in toàn bộ ghế');
        $this->patch(route('staff.tickets.update', $b))->assertRedirect();
        $this->assertDatabaseHas('tickets', ['booking_id' => $b->id, 'status' => 'used']);
        $this->assertDatabaseCount('ticket_checkins', 1);
    }

    public function test_preview_still_checks_signature_and_staff_permissions(): void
    {
        $b = $this->bookingFor();
        $this->actingAs($b->user)->post(route('staff.tickets.scan'), ['ticket_token' => $b->barcodeValue(), 'intent' => 'preview'])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'staff']))->post(route('staff.tickets.scan'), ['ticket_token' => $b->code.'-0000000000000000', 'intent' => 'preview'])->assertRedirect(route('staff.tickets.index'))->assertSessionHas('error');
        $this->assertDatabaseCount('ticket_checkins', 0);
    }
}
