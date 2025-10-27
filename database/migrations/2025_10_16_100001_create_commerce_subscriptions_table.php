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
        if (!Schema::hasTable('commerce_subscriptions')) {
            Schema::create('commerce_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('team_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('customer_id')->nullable();
                $table->string('stripe_customer_id')->nullable();
                $table->string('stripe_subscription_id')->nullable();
                $table->unsignedBigInteger('plan_id');
                $table->string('status');
                $table->timestamp('current_period_start')->nullable();
                $table->timestamp('current_period_end')->nullable();
                $table->boolean('cancel_at_period_end')->default(false);
                $table->timestamp('cancel_at')->nullable();
                $table->timestamp('trial_ends_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                // Indexes
                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'stripe_subscription_id']);
                $table->index(['tenant_id', 'stripe_customer_id']);
                $table->index(['plan_id']);

                // Foreign keys
                $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('team_id')->references('id')->on('teams')->onDelete('set null');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('plan_id')->references('id')->on('commerce_plans')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('commerce_subscriptions')) {
            Schema::dropIfExists('commerce_subscriptions');
        }
    }
};
