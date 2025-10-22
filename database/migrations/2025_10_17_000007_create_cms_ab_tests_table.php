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
        Schema::create('cms_ab_tests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('page_id')->constrained('cms_pages')->onDelete('cascade');
            $table->foreignId('variant_a_id')->constrained('cms_pages')->onDelete('cascade'); // Original version
            $table->foreignId('variant_b_id')->constrained('cms_pages')->onDelete('cascade'); // Test version
            $table->integer('traffic_split')->default(50); // Percentage for variant B (0-100)
            $table->enum('status', ['draft', 'running', 'paused', 'completed', 'archived'])->default('draft');
            $table->json('performance_data')->nullable(); // Conversion rates, metrics
            $table->json('goals')->nullable(); // Conversion goals (clicks, form submissions, etc.)
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('min_sample_size')->default(1000); // Minimum visitors before declaring winner
            $table->decimal('confidence_level', 5, 2)->default(95.00); // Statistical confidence
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['page_id', 'status']);
            $table->index(['status', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_ab_tests');
    }
};



