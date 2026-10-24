<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concessions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('price');
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('concessions_total', 12, 0)->default(0);
        });
        Schema::create('booking_concessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('concession_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('quantity');
            $table->unsignedInteger('unit_price');
            $table->timestamps();
            $table->unique(['booking_id', 'concession_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_concessions');
        Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn('concessions_total'));
        Schema::dropIfExists('concessions');
    }
};
