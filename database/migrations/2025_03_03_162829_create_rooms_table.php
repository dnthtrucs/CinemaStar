<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cinema_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('2D');
            $table->unsignedTinyInteger('rows')->default(8);
            $table->unsignedTinyInteger('seats_per_row')->default(10);
            $table->unsignedSmallInteger('total_seats')->default(80);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['cinema_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
