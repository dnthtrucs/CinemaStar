<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('movies', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('original_title')->nullable();
            $table->text('description');
            $table->string('poster')->nullable();
            $table->string('trailer_url')->nullable();
            $table->string('genre');
            $table->string('director');
            $table->text('actors')->nullable();
            $table->unsignedSmallInteger('duration');
            $table->string('age_rating', 10)->default('P');
            $table->date('release_date')->nullable();
            $table->string('country')->default('Việt Nam');
            $table->string('language')->default('Tiếng Việt');
            $table->string('status')->default('now_showing')->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movies');
    }
};
