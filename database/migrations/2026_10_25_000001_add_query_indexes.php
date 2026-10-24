<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['user_id', 'created_at', 'id'], 'bookings_user_created_index');
            $table->index(['status', 'payment_status', 'expires_at'], 'bookings_expiry_scan_index');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['status', 'paid_at', 'booking_id'], 'payments_status_paid_booking_index');
        });
        Schema::table('showtimes', function (Blueprint $table) {
            $table->index(['status', 'starts_at'], 'showtimes_status_starts_index');
        });
    }

    public function down(): void
    {
        Schema::table('showtimes', fn (Blueprint $table) => $table->dropIndex('showtimes_status_starts_index'));
        Schema::table('payments', fn (Blueprint $table) => $table->dropIndex('payments_status_paid_booking_index'));
        // InnoDB may remove its implicit FK index when a covering index is
        // introduced. Restore that index before removing the covering one.
        if (! Schema::hasIndex('bookings', ['user_id'])) {
            Schema::table('bookings', fn (Blueprint $table) => $table->index('user_id', 'bookings_user_id_foreign'));
        }
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_expiry_scan_index');
            $table->dropIndex('bookings_user_created_index');
        });
    }
};
