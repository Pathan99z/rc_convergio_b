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
        if (!Schema::hasTable('commerce_transactions')) {
            Schema::create('commerce_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->nullable();
                $table->unsignedBigInteger('payment_link_id')->nullable();
                $table->string('payment_provider');
                $table->string('provider_event_id')->nullable()->unique();
                $table->decimal('amount', 15, 2);
                $table->string('currency', 3);
                $table->string('status');
                $table->json('raw_payload')->nullable();
                $table->string('event_type')->nullable();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('team_id')->nullable()->index();
                $table->timestamps();

                // Foreign key constraints
                $table->foreign('order_id')->references('id')->on('commerce_orders')->onDelete('set null');
                $table->foreign('payment_link_id')->references('id')->on('commerce_payment_links')->onDelete('set null');
                $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('team_id')->references('id')->on('teams')->onDelete('set null');

                // Indexes for performance
                $table->index(['tenant_id', 'payment_provider']);
                $table->index(['tenant_id', 'status']);
                $table->index(['provider_event_id']);
                $table->index(['order_id']);
                $table->index(['payment_link_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commerce_transactions');
    }
};
