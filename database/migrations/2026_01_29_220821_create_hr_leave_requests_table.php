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
        if (!Schema::hasTable('hr_leave_requests')) {
            Schema::create('hr_leave_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('employee_id')->index();
                $table->unsignedBigInteger('leave_type_id')->index();
                $table->date('start_date');
                $table->date('end_date');
                $table->decimal('days_requested', 5, 2);
                $table->text('reason')->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('approved');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamps();

                // Foreign key constraints
                $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('employee_id')->references('id')->on('hr_employees')->onDelete('cascade');
                $table->foreign('leave_type_id')->references('id')->on('hr_leave_types')->onDelete('cascade');
                $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');

                // Indexes
                $table->index(['tenant_id', 'employee_id']);
                $table->index(['tenant_id', 'status']);
                $table->index(['start_date', 'end_date']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_leave_requests');
    }
};

