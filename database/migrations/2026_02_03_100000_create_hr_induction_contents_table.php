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
        if (!Schema::hasTable('hr_induction_contents')) {
            Schema::create('hr_induction_contents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('title');
                $table->text('description')->nullable();
                $table->enum('content_type', ['document', 'video', 'both'])->default('document');
                $table->enum('category', ['induction', 'policy', 'training'])->default('induction');
                $table->string('file_url')->nullable(); // For PDF/DOCX uploads
                $table->text('video_url')->nullable(); // For video links
                $table->json('support_documents')->nullable(); // Array of document IDs
                $table->enum('target_audience_type', ['all_employees', 'onboarding_only', 'department_specific'])->default('all_employees');
                $table->json('target_departments')->nullable(); // Array of department IDs if department_specific
                $table->boolean('is_mandatory')->default(false);
                $table->date('due_date')->nullable();
                $table->integer('estimated_time')->nullable()->comment('Time in minutes');
                $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
                $table->timestamp('published_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                // Foreign key constraints
                $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

                // Indexes for performance
                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'category']);
                $table->index(['tenant_id', 'target_audience_type']);
                $table->index('published_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_induction_contents');
    }
};

