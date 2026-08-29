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
                // Two physical seats make one couple seat: 2 × base price + 30,000 VND.
                'price_surcharge' => 15000,
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
