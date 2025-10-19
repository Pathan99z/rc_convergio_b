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
        if (!Schema::hasTable('commerce_subscription_events')) {
            Schema::create('commerce_subscription_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('subscription_id')->nullable();
                $table->string('event_type');
                $table->string('stripe_event_id')->unique();
                $table->json('payload');
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                // Indexes
                $table->index(['tenant_id', 'event_type']);
                $table->index(['tenant_id', 'stripe_event_id']);
                $table->index(['subscription_id']);

                // Foreign keys
                $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('subscription_id')->references('id')->on('commerce_subscriptions')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('commerce_subscription_events')) {
            Schema::dropIfExists('commerce_subscription_events');
        }
    }
};
