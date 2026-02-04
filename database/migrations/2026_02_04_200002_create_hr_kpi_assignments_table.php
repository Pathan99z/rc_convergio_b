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
        if (!Schema::hasTable('hr_kpi_assignments')) {
            Schema::create('hr_kpi_assignments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('employee_id')->index();
                $table->unsignedBigInteger('kpi_template_id')->index();
                $table->string('review_period_value'); // e.g., "Q1 2026", "January 2026", "2026"
                $table->date('review_period_start');
                $table->date('review_period_end');
                $table->enum('status', [
                    'assigned',
                    'self_review_pending',
                    'self_review_submitted',
                    'manager_review_pending',
                    'completed',
                    'overdue'
                ])->default('assigned');
                $table->unsignedBigInteger('assigned_by');
                $table->timestamps();
                $table->softDeletes();

                // Foreign key constraints
                $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('employee_id')->references('id')->on('hr_employees')->onDelete('cascade');
                $table->foreign('kpi_template_id')->references('id')->on('hr_kpi_templates')->onDelete('cascade');
                $table->foreign('assigned_by')->references('id')->on('users')->onDelete('cascade');

                // Unique constraint: One active KPI per employee per template per period
                $table->unique(['employee_id', 'kpi_template_id', 'review_period_value'], 'unique_employee_template_period');

                // Indexes for performance
                $table->index(['tenant_id', 'status']);
                $table->index(['employee_id', 'status']);
                $table->index(['kpi_template_id', 'status']);
                $table->index(['review_period_start', 'review_period_end']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_kpi_assignments');
    }
};

