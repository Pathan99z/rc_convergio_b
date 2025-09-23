<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Changes scheduled_at from TIMESTAMP to DATETIME to prevent MySQL
     * from automatically updating the column value.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Change scheduled_at from TIMESTAMP to DATETIME
            // This prevents MySQL from automatically updating the column
            $table->datetime('scheduled_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     * 
     * Reverts scheduled_at back to TIMESTAMP for rollback safety.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Revert back to TIMESTAMP (original definition)
            $table->timestamp('scheduled_at')->nullable()->change();
        });
    }
};
