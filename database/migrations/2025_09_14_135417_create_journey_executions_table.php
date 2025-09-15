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
        Schema::create('journey_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journey_id')->constrained('journeys')->onDelete('cascade');
            $table->foreignId('contact_id')->constrained('contacts')->onDelete('cascade');
            $table->foreignId('current_step_id')->nullable()->constrained('journey_steps')->onDelete('set null');
            $table->enum('status', ['running', 'paused', 'completed', 'failed', 'cancelled'])->default('running');
            $table->json('execution_data')->nullable(); // Track execution state and data
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('next_step_at')->nullable(); // When next step should execute
            $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            // Indexes
            $table->index(['journey_id', 'contact_id']);
            $table->index(['contact_id', 'status']);
            $table->index(['status', 'next_step_at']);
            $table->index(['tenant_id', 'status']);
            $table->unique(['journey_id', 'contact_id']); // One execution per contact per journey
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journey_executions');
    }
};
