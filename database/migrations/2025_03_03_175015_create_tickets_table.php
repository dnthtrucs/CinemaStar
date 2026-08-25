<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('showtime_id')->constrained()->restrictOnDelete();
            $table->foreignId('seat_id')->constrained()->restrictOnDelete();
            $table->string('code', 24)->unique();
            $table->decimal('unit_price', 10, 0);
            $table->string('qr_token', 64)->unique();
            $table->string('status')->default('valid')->index();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();

            $table->unique(['showtime_id', 'seat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
