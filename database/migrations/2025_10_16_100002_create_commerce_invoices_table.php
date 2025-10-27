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
        if (!Schema::hasTable('commerce_invoices')) {
            Schema::create('commerce_invoices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('subscription_id');
                $table->string('stripe_invoice_id')->nullable();
                $table->bigInteger('amount_cents');
                $table->string('currency', 3)->default('usd');
                $table->enum('status', ['paid', 'open', 'void', 'draft'])->default('draft');
                $table->timestamp('paid_at')->nullable();
                $table->json('raw_payload')->nullable();
                $table->timestamps();

                // Indexes
                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'stripe_invoice_id']);
                $table->index(['subscription_id']);

                // Foreign keys
                $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('subscription_id')->references('id')->on('commerce_subscriptions')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('commerce_invoices')) {
            Schema::dropIfExists('commerce_invoices');
        }
    }
};
