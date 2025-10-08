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
        Schema::table('sequence_enrollments', function (Blueprint $table) {
            $table->string('target_name')->nullable()->after('target_id');
            $table->text('notes')->nullable()->after('target_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sequence_enrollments', function (Blueprint $table) {
            $table->dropColumn(['target_name', 'notes']);
        });
    }
};
