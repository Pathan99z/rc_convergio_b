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
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->index(); // SEO-friendly URL
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->json('meta_keywords')->nullable(); // Array of keywords
            $table->enum('status', ['draft', 'published', 'scheduled', 'archived'])->default('draft');
            $table->json('json_content'); // Page layout and content
            $table->foreignId('template_id')->nullable()->constrained('cms_templates')->onDelete('set null');
            $table->foreignId('domain_id')->nullable()->constrained('cms_domains')->onDelete('set null');
            $table->foreignId('language_id')->nullable()->constrained('cms_languages')->onDelete('set null');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->json('seo_data')->nullable(); // SEO analysis results
            $table->integer('view_count')->default(0);
            $table->json('settings')->nullable(); // Page-specific settings
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['status', 'published_at']);
            $table->index(['domain_id', 'language_id']);
            $table->index(['created_by']);
            $table->unique(['slug', 'domain_id', 'language_id']); // Unique slug per domain/language
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_pages');
    }
};



