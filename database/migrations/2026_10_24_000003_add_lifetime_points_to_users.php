<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('lifetime_points')->default(0);
        });

        // Backfill in SQL so existing users keep their tier without loading all bookings.
        DB::table('users')->update([
            'lifetime_points' => DB::raw("(SELECT COALESCE(SUM(points_earned), 0) FROM bookings WHERE bookings.user_id = users.id AND bookings.payment_status = 'paid')"),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('lifetime_points'));
    }
};
