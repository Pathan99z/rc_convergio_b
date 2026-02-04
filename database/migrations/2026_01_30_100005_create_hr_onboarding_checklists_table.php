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
        if (!Schema::hasTable('hr_onboarding_checklists')) {
            Schema::create('hr_onboarding_checklists', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('employee_id')->index();
                $table->unsignedBigInteger('checklist_template_id')->index();
                $table->enum('status', ['pending', 'in_progress', 'completed', 'skipped'])->default('pending');
                $table->unsignedBigInteger('assigned_to')->nullable()->index(); // user_id
                $table->unsignedBigInteger('completed_by')->nullable()->index(); // user_id
                $table->timestamp('completed_at')->nullable();
                $table->date('due_date')->nullable();
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable(); // For additional data like document IDs
                $table->timestamps();
                $table->softDeletes();

                // Foreign key constraints
                $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('employee_id')->references('id')->on('hr_employees')->onDelete('cascade');
                $table->foreign('checklist_template_id')->references('id')->on('hr_onboarding_checklist_templates')->onDelete('cascade');
                $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
                $table->foreign('completed_by')->references('id')->on('users')->onDelete('set null');

                // Indexes for performance
                $table->index(['tenant_id', 'employee_id', 'status']);
                $table->index(['assigned_to', 'status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_onboarding_checklists');
    }
};


