<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('seats')
            ->where('row', 'H')
            ->update([
                'type' => 'couple',
                // 2 × (base price + 30,000 VND) + 30,000 VND per couple pair.
                'price_surcharge' => 45000,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('seats')
            ->where('row', 'H')
            ->update([
                'type' => 'vip',
                'price_surcharge' => 30000,
                'updated_at' => now(),
            ]);
    }
};
