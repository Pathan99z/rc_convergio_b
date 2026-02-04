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
        if (!Schema::hasTable('hr_employee_induction_assignments')) {
            Schema::create('hr_employee_induction_assignments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('employee_id')->index();
                $table->unsignedBigInteger('induction_content_id')->index();
                $table->enum('status', ['pending', 'in_progress', 'completed', 'overdue'])->default('pending');
                $table->timestamp('assigned_at');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->unsignedBigInteger('acknowledged_by')->nullable()->comment('user_id - the employee');
                $table->date('due_date')->nullable();
                $table->boolean('is_overdue')->default(false);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                // Foreign key constraints
                $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('employee_id')->references('id')->on('hr_employees')->onDelete('cascade');
                $table->foreign('induction_content_id')->references('id')->on('hr_induction_contents')->onDelete('cascade');
                $table->foreign('acknowledged_by')->references('id')->on('users')->onDelete('set null');

                // Unique constraint to prevent duplicate assignments
                $table->unique(['employee_id', 'induction_content_id'], 'unique_employee_content_assignment');

                // Indexes for performance (using short names to avoid MySQL 64-char limit)
                $table->index(['tenant_id', 'status'], 'idx_induction_tenant_status');
                $table->index(['employee_id', 'status'], 'idx_induction_employee_status');
                $table->index(['induction_content_id', 'status'], 'idx_induction_content_status');
                $table->index('due_date', 'idx_induction_due_date');
                $table->index('is_overdue', 'idx_induction_overdue');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_employee_induction_assignments');
    }
};

