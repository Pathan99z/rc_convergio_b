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
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('platform', ['facebook', 'instagram', 'twitter', 'linkedin', 'youtube', 'tiktok', 'pinterest']);
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('platform_user_id')->nullable(); // Platform-specific user ID
            $table->string('platform_username')->nullable(); // Username on the platform
            $table->json('metadata')->nullable(); // Additional platform-specific data
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->unique(['user_id', 'platform']);
            $table->index(['platform', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};


