<?php

namespace Tests\Feature;

use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class AccountSafetyTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    public function test_locked_user_loses_an_existing_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $user->update(['is_active' => false]);
        $this->get(route('dashboard'))->assertRedirect(route('login'))->assertSessionHas('status', 'Tài khoản đã bị khóa');
        $this->assertGuest();
    }

    public function test_user_with_paid_history_cannot_delete_their_account(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $booking = $this->bookingFor($user);
        $booking->update(['status' => 'confirmed', 'payment_status' => 'paid']);
        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect(route('profile.edit'))->assertSessionHasErrorsIn('userDeletion', 'password');
        $this->assertNotNull($user->fresh());
        $this->assertNotNull($booking->fresh());
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_with_refund_history_cannot_delete_their_account(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $booking = $this->bookingFor($user);
        RefundRequest::create(['booking_id' => $booking->id, 'requested_by' => $user->id, 'reason' => 'Test', 'amount' => 80000]);
        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect(route('profile.edit'))->assertSessionHasErrorsIn('userDeletion', 'password');
        $this->assertNotNull($user->fresh());
    }
}
