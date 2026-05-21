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
        Schema::table('callback_idempotency', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->after('id');
            $table->bigInteger('amount')->nullable()->after('checkout_request_id');
            $table->string('phone', 20)->nullable()->after('amount');
            $table->string('reference', 255)->nullable()->after('phone');
            $table->text('response_description')->nullable()->after('result_code');
            $table->json('callback_payload')->nullable()->after('response_description');
            $table->string('status', 20)->default('pending')->after('callback_payload');
            $table->integer('retry_count')->default(0)->after('status');
            $table->timestamp('last_retried_at')->nullable()->after('retry_count');
            $table->string('mpesa_receipt', 100)->nullable()->after('last_retried_at');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('callback_idempotency', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropColumn([
                'user_id', 'amount', 'phone', 'reference',
                'response_description', 'callback_payload', 'status',
                'retry_count', 'last_retried_at', 'mpesa_receipt',
            ]);
        });
    }
};
