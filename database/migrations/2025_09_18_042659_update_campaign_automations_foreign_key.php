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
        Schema::table('campaign_automations', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['template_id']);
            
            // Add new foreign key constraint pointing to campaigns table
            $table->foreign('template_id')->references('id')->on('campaigns')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_automations', function (Blueprint $table) {
            // Drop the campaigns foreign key
            $table->dropForeign(['template_id']);
            
            // Restore the original email_templates foreign key
            $table->foreign('template_id')->references('id')->on('email_templates')->onDelete('cascade');
        });
    }
};