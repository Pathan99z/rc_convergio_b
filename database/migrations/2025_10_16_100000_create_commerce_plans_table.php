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
        if (!Schema::hasTable('commerce_plans')) {
            Schema::create('commerce_plans', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('team_id')->nullable();
                $table->string('name');
                $table->string('slug');
                $table->string('stripe_product_id')->nullable();
                $table->string('stripe_price_id')->nullable();
                $table->enum('interval', ['monthly', 'yearly', 'weekly']);
                $table->bigInteger('amount_cents');
                $table->string('currency', 3)->default('usd');
                $table->boolean('active')->default(true);
                $table->integer('trial_days')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                // Indexes
                $table->index(['tenant_id', 'slug']);
                $table->index(['tenant_id', 'active']);
                $table->unique(['tenant_id', 'slug']);

                // Foreign keys
                $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('team_id')->references('id')->on('teams')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('commerce_plans')) {
            Schema::dropIfExists('commerce_plans');
        }
    }
};
