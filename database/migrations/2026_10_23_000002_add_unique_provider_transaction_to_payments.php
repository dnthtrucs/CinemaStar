<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicate = DB::table('payments')->select('provider', 'transaction_id')
            ->whereNotNull('transaction_id')->groupBy('provider', 'transaction_id')
            ->havingRaw('COUNT(*) > 1')->first();
        if ($duplicate) {
            throw new RuntimeException('Cannot add payments_provider_transaction_unique: duplicate (provider, transaction_id) exists for '
                .$duplicate->provider.' / '.$duplicate->transaction_id.'. Reconcile these receipts before retrying; no data has been deleted.');
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->unique(['provider', 'transaction_id'], 'payments_provider_transaction_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_provider_transaction_unique');
        });
    }
};
