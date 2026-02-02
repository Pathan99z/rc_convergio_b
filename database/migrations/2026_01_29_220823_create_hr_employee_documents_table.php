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
        if (!Schema::hasTable('hr_employee_documents')) {
            Schema::create('hr_employee_documents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('employee_id')->index();
                $table->unsignedBigInteger('document_id')->index();
                $table->enum('category', ['contract', 'id_document', 'qualification', 'performance', 'disciplinary', 'other'])->default('other');
                $table->boolean('is_hr_only')->default(false);
                $table->unsignedBigInteger('created_by');
                $table->timestamps();

                // Foreign key constraints
                $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('employee_id')->references('id')->on('hr_employees')->onDelete('cascade');
                $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');

                // Indexes
                $table->index(['tenant_id', 'employee_id']);
                $table->index(['category', 'is_hr_only']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_employee_documents');
    }
};

