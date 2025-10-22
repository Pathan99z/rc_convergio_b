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
        Schema::create('cms_personalization_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('cms_pages')->onDelete('cascade');
            $table->string('section_id'); // Which section of the page this rule applies to
            $table->string('name'); // Rule name for identification
            $table->text('description')->nullable();
            $table->json('conditions'); // Array of conditions (country, device, referrer, etc.)
            $table->json('variant_data'); // The content to show when conditions match
            $table->integer('priority')->default(0); // Rule execution priority
            $table->boolean('is_active')->default(true);
            $table->json('performance_data')->nullable(); // Conversion tracking
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['page_id', 'is_active']);
            $table->index(['section_id']);
            $table->index(['priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_personalization_rules');
    }
};



