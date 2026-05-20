<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('account_type', 50);
            $table->string('account_name', 255);
            $table->string('account_code', 20)->unique();
            $table->string('normal_balance', 10); // debit or credit
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('transaction_type', 50);
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('description');
            $table->string('status', 20); // pending, completed, failed
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('entries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('transaction_id');
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('debit_amount')->default(0);
            $table->unsignedBigInteger('credit_amount')->default(0);
            $table->bigInteger('balance_after')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->unique(['transaction_id', 'account_id']);
            $table->index('account_id');
        });

        Schema::create('withdrawals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('amount'); // cents
            $table->string('status', 20); // pending, processing, completed, failed, cancelled
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->foreign('seller_id')->references('id')->on('sellers')->onDelete('cascade');
            $table->index('status');
        });

        Schema::create('callback_idempotency', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('correlation_id', 255)->unique();
            $table->string('checkout_request_id', 255)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->integer('result_code')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('callback_idempotency');
        Schema::dropIfExists('withdrawals');
        Schema::dropIfExists('entries');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('accounts');
    }
};
