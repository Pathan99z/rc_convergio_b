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
        if (!Schema::hasTable('hr_kpi_templates')) {
            Schema::create('hr_kpi_templates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('name'); // e.g., "Senior Software Engineer - Tech Assessment"
                $table->unsignedBigInteger('department_id')->nullable()->index();
                $table->unsignedBigInteger('designation_id')->nullable()->index();
                $table->enum('review_period', ['monthly', 'quarterly', 'yearly', 'once'])->default('quarterly');
                $table->text('description')->nullable();
                $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
                $table->unsignedBigInteger('created_by');
                $table->timestamps();
                $table->softDeletes();

                // Foreign key constraints
                $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('department_id')->references('id')->on('hr_departments')->onDelete('set null');
                $table->foreign('designation_id')->references('id')->on('hr_designations')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');

                // Indexes
                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'review_period']);
                $table->index(['department_id', 'designation_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_kpi_templates');
    }
};

