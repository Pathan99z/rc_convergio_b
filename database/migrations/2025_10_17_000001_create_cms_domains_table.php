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
        Schema::create('cms_domains', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., 'example.com'
            $table->string('display_name')->nullable(); // Friendly name
            $table->enum('ssl_status', ['none', 'pending', 'active', 'failed'])->default('none');
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable(); // Custom domain settings
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['is_active', 'is_primary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_domains');
    }
};



