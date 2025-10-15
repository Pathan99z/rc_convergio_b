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
        Schema::create('social_media_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('platform', ['facebook', 'instagram', 'twitter', 'linkedin', 'youtube', 'tiktok', 'pinterest']);
            $table->string('metric_name'); // e.g., 'followers', 'likes', 'shares', 'impressions'
            $table->string('metric_value'); // Store as string to handle various data types
            $table->date('metric_date'); // Date for the metric
            $table->string('post_id')->nullable(); // If metric is for a specific post
            $table->json('additional_data')->nullable(); // Any extra contextual data
            $table->timestamps();

            // Indexes for efficient querying
            $table->index(['user_id', 'platform', 'metric_date']);
            $table->index(['user_id', 'platform', 'metric_name']);
            $table->index(['post_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_media_analytics');
    }
};


