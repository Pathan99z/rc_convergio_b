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
        Schema::create('social_media_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('content');
            $table->enum('platform', ['facebook', 'twitter', 'instagram', 'linkedin', 'youtube', 'tiktok', 'pinterest']);
            $table->json('hashtags')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->json('media_urls')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'published', 'failed'])->default('draft');
            $table->string('external_post_id')->nullable(); // ID from the social media platform
            $table->json('engagement_metrics')->nullable(); // likes, shares, comments, etc.
            $table->text('target_audience')->nullable(); // Target audience description
            $table->string('call_to_action')->nullable(); // Call to action text
            $table->string('location')->nullable(); // Location tag
            $table->json('mentions')->nullable(); // User mentions
            $table->timestamps();

            // Indexes for better performance
            $table->index(['user_id', 'platform']);
            $table->index(['user_id', 'status']);
            $table->index(['scheduled_at']);
            $table->index(['published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_media_posts');
    }
};
