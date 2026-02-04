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
        if (!Schema::hasTable('hr_document_types')) {
            Schema::create('hr_document_types', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('name'); // e.g., "Offer Letter", "Payslip", "ID Proof"
                $table->string('code')->nullable(); // e.g., "OFFER_LETTER", "PAYSLIP"
                $table->text('description')->nullable();
                $table->enum('category', [
                    'contract',
                    'id_document',
                    'qualification',
                    'performance',
                    'disciplinary',
                    'onboarding',
                    'profile_picture',
                    'payslip',
                    'other'
                ])->default('other');
                $table->boolean('is_mandatory')->default(false);
                $table->boolean('employee_can_upload')->default(true); // Can employee self-upload?
                $table->boolean('is_hr_only')->default(false); // HR-only visibility
                $table->json('allowed_file_types')->nullable(); // e.g., ["pdf", "jpg", "png"]
                $table->integer('max_file_size_mb')->default(10); // Max file size in MB
                $table->json('target_departments')->nullable(); // Department IDs (null = all departments)
                $table->json('target_designations')->nullable(); // Designation IDs (null = all designations)
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by');
                $table->timestamps();
                $table->softDeletes();

                // Foreign key constraints
                $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');

                // Indexes
                $table->index(['tenant_id', 'is_active']);
                $table->index(['tenant_id', 'category']);
                $table->index(['tenant_id', 'is_mandatory']);
                $table->unique(['tenant_id', 'code']); // Unique code per tenant
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_document_types');
    }
};

