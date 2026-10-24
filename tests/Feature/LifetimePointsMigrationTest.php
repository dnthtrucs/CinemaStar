<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class LifetimePointsMigrationTest extends TestCase
{
    use CreatesCinemaBookings, DatabaseMigrations;

    public function test_backfill_counts_only_paid_awards_and_rollback_keeps_existing_data(): void
    {
        $path = database_path('migrations/2026_10_24_000003_add_lifetime_points_to_users.php');
        $this->assertFileExists($path);
        $migration = require $path;
        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'lifetime_points'));

        $customer = User::factory()->create(['loyalty_points' => 999]);
        $onlyRefunded = User::factory()->create(['loyalty_points' => 40]);
        $noBookings = User::factory()->create();
        $paid = $this->bookingFor($customer);
        $paid->update(['status' => 'confirmed', 'payment_status' => 'paid', 'points_earned' => 123]);
        foreach ([
            [$customer->id, 'paid', 200], [$customer->id, 'refunded', 88],
            [$customer->id, 'unpaid', 77], [$onlyRefunded->id, 'refunded', 1000],
        ] as [$userId, $state, $points]) {
            $copy = $paid->replicate();
            $copy->forceFill(['user_id' => $userId, 'code' => 'BK'.Str::upper(Str::random(16)),
                'payment_status' => $state, 'points_earned' => $points])->save();
        }
        $usersBefore = DB::table('users')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        $bookingsBefore = DB::table('bookings')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();

        $migration->up();

        $this->assertSame(323, (int) DB::table('users')->where('id', $customer->id)->value('lifetime_points'));
        $this->assertSame(0, (int) DB::table('users')->where('id', $onlyRefunded->id)->value('lifetime_points'));
        $this->assertSame(0, (int) DB::table('users')->where('id', $noBookings->id)->value('lifetime_points'));
        $this->assertSame(999, (int) DB::table('users')->where('id', $customer->id)->value('loyalty_points'));
        $this->assertSame($bookingsBefore, DB::table('bookings')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all());
        $newCustomer = User::factory()->create();
        $this->assertSame(0, (int) DB::table('users')->where('id', $newCustomer->id)->value('lifetime_points'));
        $newCustomer->delete();

        $migration->down();

        $this->assertFalse(Schema::hasColumn('users', 'lifetime_points'));
        $this->assertSame($usersBefore, DB::table('users')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all());
        $this->assertSame($bookingsBefore, DB::table('bookings')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all());
        $migration->up();
        $this->assertSame(323, (int) DB::table('users')->where('id', $customer->id)->value('lifetime_points'));
    }
}
