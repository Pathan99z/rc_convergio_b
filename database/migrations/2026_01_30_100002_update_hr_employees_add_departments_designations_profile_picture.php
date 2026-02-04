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
        Schema::table('hr_employees', function (Blueprint $table) {
            // Add department_id (keep old 'department' column for backward compatibility)
            if (!Schema::hasColumn('hr_employees', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable()->after('department');
                $table->index('department_id');
                $table->foreign('department_id')->references('id')->on('hr_departments')->onDelete('set null');
            }

            // Add designation_id (keep old 'job_title' column for backward compatibility)
            if (!Schema::hasColumn('hr_employees', 'designation_id')) {
                $table->unsignedBigInteger('designation_id')->nullable()->after('job_title');
                $table->index('designation_id');
                $table->foreign('designation_id')->references('id')->on('hr_designations')->onDelete('set null');
            }

            // Add profile_picture_id
            if (!Schema::hasColumn('hr_employees', 'profile_picture_id')) {
                $table->unsignedBigInteger('profile_picture_id')->nullable()->after('user_id');
                $table->index('profile_picture_id');
                $table->foreign('profile_picture_id')->references('id')->on('documents')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            if (Schema::hasColumn('hr_employees', 'profile_picture_id')) {
                $table->dropForeign(['profile_picture_id']);
                $table->dropIndex(['profile_picture_id']);
                $table->dropColumn('profile_picture_id');
            }

            if (Schema::hasColumn('hr_employees', 'designation_id')) {
                $table->dropForeign(['designation_id']);
                $table->dropIndex(['designation_id']);
                $table->dropColumn('designation_id');
            }

            if (Schema::hasColumn('hr_employees', 'department_id')) {
                $table->dropForeign(['department_id']);
                $table->dropIndex(['department_id']);
                $table->dropColumn('department_id');
            }
        });
    }
};


