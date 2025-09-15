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
        Schema::create('lead_scoring_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('condition'); // Rule condition (event, criteria, etc.)
            $table->integer('points')->default(0); // Points to award
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0); // Rule execution priority
            $table->json('metadata')->nullable(); // Additional rule configuration
            $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            // Indexes
            $table->index(['tenant_id', 'is_active']);
            $table->index(['priority', 'is_active']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_scoring_rules');
    }
};
