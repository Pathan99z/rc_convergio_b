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
        Schema::table('hr_designations', function (Blueprint $table) {
            // Add department_id (nullable for flexibility - allows shared designations)
            if (!Schema::hasColumn('hr_designations', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable()->after('code');
                $table->index('department_id');
                $table->foreign('department_id')
                    ->references('id')
                    ->on('hr_departments')
                    ->onDelete('set null'); // If department deleted, set to null
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hr_designations', function (Blueprint $table) {
            if (Schema::hasColumn('hr_designations', 'department_id')) {
                $table->dropForeign(['department_id']);
                $table->dropIndex(['department_id']);
                $table->dropColumn('department_id');
            }
        });
    }
};

