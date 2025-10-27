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
        Schema::create('listening_mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listening_keyword_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('platform', ['facebook', 'instagram', 'twitter', 'linkedin', 'youtube', 'tiktok', 'pinterest']);
            $table->string('external_id')->nullable(); // Platform's post/mention ID
            $table->text('content');
            $table->string('author_name')->nullable();
            $table->string('author_handle')->nullable();
            $table->string('author_url')->nullable();
            $table->string('post_url')->nullable();
            $table->enum('sentiment', ['positive', 'neutral', 'negative'])->nullable();
            $table->json('engagement')->nullable(); // likes, shares, comments, etc.
            $table->timestamp('mentioned_at')->nullable(); // When the mention was posted
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'is_read']);
            $table->index(['listening_keyword_id', 'platform']);
            $table->index('mentioned_at');
            $table->unique(['platform', 'external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listening_mentions');
    }
};


