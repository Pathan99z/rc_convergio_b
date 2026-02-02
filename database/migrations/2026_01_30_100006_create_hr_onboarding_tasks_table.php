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
        if (!Schema::hasTable('hr_onboarding_tasks')) {
            Schema::create('hr_onboarding_tasks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('employee_id')->index();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('task_type')->default('hr_task'); // hr_task, manager_task, it_task, finance_task, legal_task
                $table->unsignedBigInteger('assigned_to')->nullable()->index(); // user_id
                $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
                $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
                $table->date('due_date')->nullable();
                $table->unsignedBigInteger('completed_by')->nullable()->index(); // user_id
                $table->timestamp('completed_at')->nullable();
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable(); // For additional data
                $table->unsignedBigInteger('created_by');
                $table->timestamps();
                $table->softDeletes();

                // Foreign key constraints
                $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('employee_id')->references('id')->on('hr_employees')->onDelete('cascade');
                $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
                $table->foreign('completed_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');

                // Indexes for performance
                $table->index(['tenant_id', 'employee_id', 'status']);
                $table->index(['assigned_to', 'status']);
                $table->index(['task_type', 'status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_onboarding_tasks');
    }
};

