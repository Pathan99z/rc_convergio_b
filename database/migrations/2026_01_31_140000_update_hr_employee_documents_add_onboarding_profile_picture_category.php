<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify the ENUM column to include new values
        // MySQL requires dropping and recreating the column to modify ENUM values
        if (Schema::hasTable('hr_employee_documents')) {
            // For MySQL, we need to use raw SQL to alter ENUM
            DB::statement("ALTER TABLE `hr_employee_documents` MODIFY COLUMN `category` ENUM('contract', 'id_document', 'qualification', 'performance', 'disciplinary', 'profile_picture', 'onboarding', 'other') DEFAULT 'other'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original ENUM values
        if (Schema::hasTable('hr_employee_documents')) {
            // First, update any records with new values to 'other'
            DB::table('hr_employee_documents')
                ->whereIn('category', ['profile_picture', 'onboarding'])
                ->update(['category' => 'other']);
            
            // Then revert the ENUM
            DB::statement("ALTER TABLE `hr_employee_documents` MODIFY COLUMN `category` ENUM('contract', 'id_document', 'qualification', 'performance', 'disciplinary', 'other') DEFAULT 'other'");
        }
    }
};

