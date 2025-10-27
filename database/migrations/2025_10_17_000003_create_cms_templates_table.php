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
        Schema::create('cms_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('type', ['page', 'landing', 'blog', 'email', 'popup'])->default('page');
            $table->text('description')->nullable();
            $table->json('json_structure'); // Template layout and components
            $table->string('thumbnail')->nullable(); // Preview image URL
            $table->boolean('is_system')->default(false); // System templates vs user templates
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable(); // Template-specific settings
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['type', 'is_active']);
            $table->index(['created_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_templates');
    }
};



