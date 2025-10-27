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
        Schema::create('cms_languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique(); // e.g., 'en', 'es', 'fr'
            $table->string('name'); // e.g., 'English', 'Spanish', 'French'
            $table->string('native_name')->nullable(); // e.g., 'Español', 'Français'
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('flag_icon')->nullable(); // Flag emoji or icon class
            $table->json('settings')->nullable(); // Language-specific settings
            $table->timestamps();

            // Indexes
            $table->index(['is_active', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_languages');
    }
};



