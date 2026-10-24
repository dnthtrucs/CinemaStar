<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LifetimePointsQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_and_admin_users_do_not_query_a_sum_of_booking_points(): void
    {
        $customer = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $queries = [];
        DB::listen(function (QueryExecuted $event) use (&$queries) {
            $queries[] = strtolower($event->sql);
        });

        $this->actingAs($customer)->get('/profile')->assertOk()->assertSee('Hạng Đồng');
        $this->actingAs($admin)->get('/admin/users')->assertOk();

        $sums = array_filter($queries, fn (string $query) => str_contains($query, 'sum(') && str_contains($query, 'bookings'));
        $this->assertSame([], array_values($sums), 'Profile and admin user lists must use the persisted counter instead of SUM(bookings.points_earned).');
    }
}
