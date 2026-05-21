<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('mpesa_transaction_id', 100)->nullable()->after('reversal_failure_reason');
            $table->unsignedInteger('reversal_attempts')->default(0)->after('mpesa_transaction_id');
            $table->timestamp('reversal_retry_at')->nullable()->after('reversal_attempts');
            $table->timestamp('reversal_resolved_at')->nullable()->after('reversal_retry_at');
            $table->string('reversal_resolution_type', 20)->nullable()->after('reversal_resolved_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'mpesa_transaction_id',
                'reversal_attempts',
                'reversal_retry_at',
                'reversal_resolved_at',
                'reversal_resolution_type',
            ]);
        });
    }
};
