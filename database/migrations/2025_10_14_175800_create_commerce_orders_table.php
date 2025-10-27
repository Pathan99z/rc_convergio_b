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
        if (!Schema::hasTable('commerce_orders')) {
            Schema::create('commerce_orders', function (Blueprint $table) {
                $table->id();
                $table->string('order_number')->unique();
                $table->unsignedBigInteger('deal_id')->nullable();
                $table->unsignedBigInteger('quote_id')->nullable();
                $table->unsignedBigInteger('contact_id')->nullable();
                $table->decimal('total_amount', 15, 2);
                $table->string('currency', 3)->default('USD');
                $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
                $table->string('payment_method')->nullable();
                $table->string('payment_reference')->nullable();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('team_id')->nullable()->index();
                $table->unsignedBigInteger('owner_id')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();

                // Foreign key constraints
                $table->foreign('deal_id')->references('id')->on('deals')->onDelete('set null');
                $table->foreign('quote_id')->references('id')->on('quotes')->onDelete('set null');
                $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('set null');
                $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('team_id')->references('id')->on('teams')->onDelete('set null');
                $table->foreign('owner_id')->references('id')->on('users')->onDelete('set null');

                // Indexes for performance
                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'created_at']);
                $table->index(['tenant_id', 'team_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commerce_orders');
    }
};
