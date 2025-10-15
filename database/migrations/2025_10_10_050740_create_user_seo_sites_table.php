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
        Schema::create('user_seo_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('site_url');
            $table->string('site_name')->nullable();
            $table->boolean('is_connected')->default(false);
            $table->string('gsc_property')->nullable(); // Google Search Console property
            $table->timestamp('last_synced')->nullable();
            $table->json('crawl_data')->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'site_url']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_seo_sites');
    }
};
