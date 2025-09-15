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
        Schema::create('visitor_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('cascade');
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->onDelete('cascade');
            $table->string('page_url');
            $table->integer('duration_seconds')->default(0);
            $table->enum('action', ['visit', 'download', 'form_fill', 'click', 'scroll', 'hover'])->default('visit');
            $table->integer('score')->default(0); // Intent score 0-100
            $table->json('metadata')->nullable(); // Additional tracking data
            $table->string('session_id')->nullable(); // Track user sessions
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['tenant_id', 'created_at']);
            $table->index(['company_id', 'action']);
            $table->index(['contact_id', 'action']);
            $table->index(['page_url', 'action']);
            $table->index(['session_id']);
            $table->index(['score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitor_intents');
    }
};
