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
        Schema::table('campaigns', function (Blueprint $table) {
            // Add archived_at column
            $table->timestamp('archived_at')->nullable()->after('sent_at');
            
            // Add test_sent_count column
            $table->integer('test_sent_count')->default(0)->after('bounced_count');
            
            // Add archived status to enum
            $table->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'cancelled', 'archived'])->default('draft')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            // Remove the new columns
            $table->dropColumn(['archived_at', 'test_sent_count']);
            
            // Revert status enum to original values
            $table->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'cancelled'])->default('draft')->change();
        });
    }
};