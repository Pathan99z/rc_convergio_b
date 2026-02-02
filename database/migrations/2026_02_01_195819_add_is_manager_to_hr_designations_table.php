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
            $table->boolean('is_manager')->default(false)->after('is_active');
            $table->index(['tenant_id', 'is_manager']); // For performance when filtering managers
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hr_designations', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'is_manager']);
            $table->dropColumn('is_manager');
        });
    }
};
