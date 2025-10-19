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
        if (!Schema::hasTable('commerce_settings')) {
            Schema::create('commerce_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->unique();
                $table->string('stripe_public_key')->nullable();
                $table->string('stripe_secret_key')->nullable();
                $table->enum('mode', ['test', 'live'])->default('test');
                $table->timestamps();

                // Foreign key constraints
                $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');

                // Indexes for performance
                $table->index(['tenant_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commerce_settings');
    }
};
