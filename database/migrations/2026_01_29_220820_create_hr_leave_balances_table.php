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
        if (!Schema::hasTable('hr_leave_balances')) {
            Schema::create('hr_leave_balances', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('employee_id')->index();
                $table->unsignedBigInteger('leave_type_id')->index();
                $table->decimal('balance', 8, 2)->default(0);
                $table->decimal('accrued_this_year', 8, 2)->default(0);
                $table->decimal('used_this_year', 8, 2)->default(0);
                $table->date('last_accrual_date')->nullable();
                $table->timestamps();

                // Foreign key constraints
                $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('employee_id')->references('id')->on('hr_employees')->onDelete('cascade');
                $table->foreign('leave_type_id')->references('id')->on('hr_leave_types')->onDelete('cascade');

                // Unique constraint
                $table->unique(['employee_id', 'leave_type_id']);

                // Indexes
                $table->index(['tenant_id', 'employee_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_leave_balances');
    }
};

