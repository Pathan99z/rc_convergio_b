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
        Schema::create('intent_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type'); // page_view, download, form_submit, email_open, etc.
            $table->string('event_name');
            $table->json('event_data'); // Event-specific data
            $table->integer('intent_score')->default(0); // Calculated intent score
            $table->string('source')->nullable(); // website, email, social, etc.
            $table->string('session_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('metadata')->nullable(); // Additional metadata
            $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('cascade');
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            // Indexes
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'event_type']);
            $table->index(['company_id', 'event_type']);
            $table->index(['contact_id', 'event_type']);
            $table->index(['session_id']);
            $table->index(['intent_score']);
            $table->index(['source']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('intent_events');
    }
};

