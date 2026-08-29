<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('seats')->where('type', 'couple')->update([
            'price_surcharge' => 45000,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('seats')->where('type', 'couple')->update([
            'price_surcharge' => 15000,
            'updated_at' => now(),
        ]);
    }
};
