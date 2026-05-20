<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('buyer_id');
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->string('status', 30);
            $table->text('item_description');
            $table->unsignedBigInteger('price'); // cents
            $table->unsignedBigInteger('flat_fee'); // cents, copied from config at creation
            $table->string('delivery_type', 20); // shop_delivery or g4s
            $table->text('delivery_location')->nullable();
            $table->json('delivery_location_coords')->nullable();
            $table->timestamp('expiry_at')->nullable();
            $table->timestamp('payment_expiry_at')->nullable();
            $table->timestamp('seller_accepted_at')->nullable();
            $table->timestamp('buyer_accepted_at')->nullable();
            $table->string('carrier_name', 255)->nullable();
            $table->string('carrier_phone', 20)->nullable();
            $table->string('g4s_branch', 255)->nullable();
            $table->string('g4s_tracking_ref', 255)->nullable();
            $table->timestamp('g4s_pickup_confirmed_at')->nullable();
            $table->timestamp('auto_release_at')->nullable();
            $table->boolean('auto_release_enabled')->default(false);
            $table->string('release_confirmation_token', 10)->nullable();
            $table->timestamp('release_confirmation_expires_at')->nullable();
            $table->timestamp('reversal_failed_at')->nullable();
            $table->text('reversal_failure_reason')->nullable();
            $table->string('initiator_type', 20); // buyer or seller
            $table->timestamps();

            $table->foreign('buyer_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('seller_id')->references('id')->on('sellers')->onDelete('cascade');
            $table->foreign('agent_id')->references('id')->on('agents')->onDelete('set null');

            $table->index('buyer_id');
            $table->index('seller_id');
            $table->index('agent_id');
            $table->index('status');
            $table->index('expiry_at');
        });

        Schema::create('order_templates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('seller_id');
            $table->string('template_name', 255);
            $table->text('item_description');
            $table->unsignedBigInteger('price'); // cents
            $table->string('delivery_type', 20);
            $table->text('delivery_location')->nullable();
            $table->timestamps();

            $table->foreign('seller_id')->references('id')->on('sellers')->onDelete('cascade');
            $table->index('seller_id');
        });

        Schema::create('order_issue_reports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('agent_id');
            $table->string('issue_type', 30);
            $table->text('description');
            $table->string('status', 20);
            $table->timestamp('reported_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('agent_id')->references('id')->on('agents')->onDelete('cascade');
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('sender_id');
            $table->string('sender_type', 20); // buyer, seller, agent, admin
            $table->text('message');
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('order_issue_reports');
        Schema::dropIfExists('order_templates');
        Schema::dropIfExists('orders');
    }
};
