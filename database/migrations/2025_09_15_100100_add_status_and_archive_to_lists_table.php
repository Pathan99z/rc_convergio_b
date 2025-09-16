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
        Schema::table('lists', function (Blueprint $table) {
            // Add status column
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active')->after('type');
            
            // Add archived_at column
            $table->timestamp('archived_at')->nullable()->after('updated_at');
            
            // Add cancelled_at column
            $table->timestamp('cancelled_at')->nullable()->after('archived_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lists', function (Blueprint $table) {
            $table->dropColumn(['status', 'archived_at', 'cancelled_at']);
        });
    }
};

