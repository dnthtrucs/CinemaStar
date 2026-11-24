<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->json('favorite_genres')->nullable());
        Schema::table('seats', fn (Blueprint $t) => $t->boolean('is_accessible')->default(false));
        Schema::create('booking_exchanges', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_id')->constrained()->restrictOnDelete();
            $t->foreignId('from_showtime_id')->constrained('showtimes')->restrictOnDelete();
            $t->foreignId('to_showtime_id')->constrained('showtimes')->restrictOnDelete();
            $t->json('seat_ids');
            $t->json('snapshot');
            $t->bigInteger('difference');
            $t->string('status')->default('pending')->index();
            $t->text('reason');
            $t->text('resolution')->nullable();
            $t->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('handled_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_exchanges');
        Schema::table('seats', fn (Blueprint $t) => $t->dropColumn('is_accessible'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('favorite_genres'));
    }
};
