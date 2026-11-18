<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->date('end_date')->nullable();
            $table->boolean('auto_schedule')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('movies', fn (Blueprint $table) => $table->dropColumn(['end_date', 'auto_schedule']));
    }
};
