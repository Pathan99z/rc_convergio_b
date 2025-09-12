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
        Schema::create('contact_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contact_id');
            $table->boolean('unsubscribed')->default(false);
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('unsubscribed_via')->nullable(); // 'email_link', 'manual', 'api', etc.
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable(); // Additional context
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['contact_id', 'unsubscribed']);
            $table->index('unsubscribed');
            $table->index('unsubscribed_at');
            
            // Foreign key
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_subscriptions');
    }
};
