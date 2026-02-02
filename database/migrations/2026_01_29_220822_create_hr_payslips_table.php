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
        if (!Schema::hasTable('hr_payslips')) {
            Schema::create('hr_payslips', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('employee_id')->index();
                $table->string('payslip_number')->unique();
                $table->date('pay_period_start');
                $table->date('pay_period_end');
                $table->unsignedBigInteger('document_id')->index();
                $table->unsignedBigInteger('uploaded_by');
                $table->timestamp('uploaded_at');
                $table->timestamps();

                // Foreign key constraints
                $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('employee_id')->references('id')->on('hr_employees')->onDelete('cascade');
                $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
                $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('cascade');

                // Indexes
                $table->index(['tenant_id', 'employee_id']);
                $table->index(['pay_period_start', 'pay_period_end']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_payslips');
    }
};

