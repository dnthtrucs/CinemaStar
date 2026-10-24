<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('refund_resolved_at')->nullable()->index();
            $table->foreignId('refund_resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('refund_resolution_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('refund_resolved_by');
            $table->dropColumn(['refund_resolved_at', 'refund_resolution_note']);
        });
    }
};
