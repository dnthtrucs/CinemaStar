<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('seats')->whereIn('row', ['E', 'F', 'G'])->update([
            'type' => 'vip',
            'price_surcharge' => 30000,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('seats')->whereIn('row', ['E', 'F'])->update([
            'type' => 'standard',
            'price_surcharge' => 0,
            'updated_at' => now(),
        ]);
    }
};
