<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('loyalty_points')->default(0)->after('is_active');
        });
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->enum('type', ['fixed', 'percent'])->default('fixed');
            $table->unsignedInteger('value');
            $table->unsignedInteger('min_order')->default(0);
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('voucher_id')->nullable()->after('showtime_id')->constrained()->nullOnDelete();
            $table->string('refund_status')->nullable()->after('payment_status')->index();
            $table->text('refund_reason')->nullable();
            $table->timestamp('refund_requested_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) { $table->dropConstrainedForeignId('voucher_id'); $table->dropColumn(['refund_status','refund_reason','refund_requested_at','refunded_at']); });
        Schema::dropIfExists('vouchers');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('loyalty_points'));
    }
};
