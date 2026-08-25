<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('refund_requests', function (Blueprint $table) {
            $table->id(); $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users'); $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['requested','approved','refunded','rejected'])->default('requested')->index();
            $table->text('reason'); $table->text('admin_note')->nullable(); $table->unsignedBigInteger('amount');
            $table->timestamp('handled_at')->nullable(); $table->timestamps();
        });
        Schema::create('movie_reviews', function (Blueprint $table) {
            $table->id(); $table->foreignId('movie_id')->constrained()->cascadeOnDelete(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating'); $table->text('comment')->nullable();
            $table->enum('status', ['pending','approved','rejected'])->default('pending')->index(); $table->timestamps();
            $table->unique(['movie_id','user_id']);
        });
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 100)->index(); $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description'); $table->json('properties')->nullable(); $table->ipAddress('ip_address')->nullable(); $table->timestamps();
        });
        Schema::create('ticket_checkins', function (Blueprint $table) {
            $table->id(); $table->foreignId('ticket_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('checked_in_at'); $table->timestamps();
        });
        Schema::create('voucher_usages', function (Blueprint $table) {
            $table->id(); $table->foreignId('voucher_id')->constrained()->cascadeOnDelete(); $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->unsignedBigInteger('discount_amount'); $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('voucher_usages'); Schema::dropIfExists('ticket_checkins'); Schema::dropIfExists('activity_logs'); Schema::dropIfExists('movie_reviews'); Schema::dropIfExists('refund_requests'); }
};
