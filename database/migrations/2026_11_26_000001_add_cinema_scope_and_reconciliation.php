<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('cinema_id')->nullable()->constrained()->nullOnDelete();
        });
        Schema::create('payment_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('resolved_by')->constrained('users')->restrictOnDelete();
            $table->string('bank_reference', 120);
            $table->text('note');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reconciliations');
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('cinema_id'));
    }
};
