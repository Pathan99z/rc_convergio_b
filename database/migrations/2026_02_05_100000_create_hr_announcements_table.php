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
        if (!Schema::hasTable('hr_announcements')) {
            Schema::create('hr_announcements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('title');
                $table->enum('category', ['birthday', 'welcome', 'policy', 'event', 'general'])->default('general');
                $table->text('message'); // Rich text content
                $table->string('attachment_url')->nullable();
                $table->enum('target_audience_type', ['all_employees', 'department_specific', 'individual'])->default('all_employees');
                $table->json('target_departments')->nullable(); // Array of department IDs
                $table->json('target_employee_ids')->nullable(); // Array of employee IDs
                $table->boolean('is_mandatory')->default(false); // Must acknowledge
                $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
                $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
                $table->timestamp('scheduled_publish_at')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->unsignedBigInteger('published_by')->nullable();
                $table->unsignedBigInteger('created_by');
                $table->timestamps();
                $table->softDeletes();

                // Indexes
                $table->index(['tenant_id', 'status'], 'idx_announcement_tenant_status');
                $table->index(['tenant_id', 'category'], 'idx_announcement_tenant_category');
                $table->index('published_at', 'idx_announcement_published_at');
                $table->index('scheduled_publish_at', 'idx_announcement_scheduled');

                // Foreign keys
                $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('published_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_announcements');
    }
};

