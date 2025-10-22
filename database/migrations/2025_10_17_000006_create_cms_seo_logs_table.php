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
        Schema::create('cms_seo_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('cms_pages')->onDelete('cascade');
            $table->json('analysis_results'); // SEO recommendations and scores
            $table->integer('seo_score')->nullable(); // Overall SEO score (0-100)
            $table->json('issues_found'); // Array of SEO issues
            $table->json('recommendations'); // Array of improvement suggestions
            $table->json('keywords_analysis')->nullable(); // Keyword density, etc.
            $table->timestamp('analyzed_at');
            $table->string('analyzer_version')->default('1.0'); // Track analyzer version
            $table->timestamps();

            // Indexes
            $table->index(['page_id', 'analyzed_at']);
            $table->index(['seo_score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_seo_logs');
    }
};



