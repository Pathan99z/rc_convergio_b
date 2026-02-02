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
        Schema::table('hr_employee_documents', function (Blueprint $table) {
            $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('pending')->after('is_hr_only');
            $table->text('rejection_reason')->nullable()->after('verification_status');
            $table->unsignedBigInteger('verified_by')->nullable()->after('rejection_reason');
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->unsignedBigInteger('rejected_by')->nullable()->after('verified_at');
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');

            // Foreign key constraints
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('rejected_by')->references('id')->on('users')->onDelete('set null');

            // Indexes for performance
            $table->index('verification_status');
            $table->index(['employee_id', 'verification_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hr_employee_documents', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropForeign(['rejected_by']);
            $table->dropIndex(['verification_status']);
            $table->dropIndex(['employee_id', 'verification_status']);
            $table->dropColumn([
                'verification_status',
                'rejection_reason',
                'verified_by',
                'verified_at',
                'rejected_by',
                'rejected_at',
            ]);
        });
    }
};
