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
        if (!Schema::hasTable('commerce_payment_links')) {
            Schema::create('commerce_payment_links', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('quote_id');
                $table->string('stripe_session_id')->nullable();
                $table->string('url')->nullable();
                $table->enum('status', ['draft', 'active', 'completed', 'expired'])->default('draft');
                $table->timestamp('expires_at')->nullable();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('team_id')->nullable()->index();
                $table->timestamps();

                // Foreign key constraints
                $table->foreign('quote_id')->references('id')->on('quotes')->onDelete('cascade');
                $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('team_id')->references('id')->on('teams')->onDelete('set null');

                // Indexes for performance
                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'team_id']);
                $table->index(['quote_id']);
                $table->index(['stripe_session_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commerce_payment_links');
    }
};
