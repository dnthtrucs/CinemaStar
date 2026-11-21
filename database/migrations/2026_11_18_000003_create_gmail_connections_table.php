<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gmail_connections', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->unique();
            $table->string('email');
            $table->string('client_id');
            $table->text('refresh_token');
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gmail_connections');
    }
};
