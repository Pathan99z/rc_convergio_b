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
            // Add template support fields
            $table->unsignedBigInteger('template_id')->nullable()->after('campaign_id');
            $table->enum('content_type', ['template', 'campaign'])->default('template')->after('template_id');
            
            // Make campaign_id nullable for template-only automations
            $table->unsignedBigInteger('campaign_id')->nullable()->change();
            
            // Add foreign key for template
            $table->foreign('template_id')->references('id')->on('email_templates')->onDelete('cascade');
            
            // Add index for template_id
            $table->index('template_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_automations', function (Blueprint $table) {
            // Drop foreign key and index
            $table->dropForeign(['template_id']);
            $table->dropIndex(['template_id']);
            
            // Drop new columns
            $table->dropColumn(['template_id', 'content_type']);
            
            // Revert campaign_id to not nullable (if needed)
            $table->unsignedBigInteger('campaign_id')->nullable(false)->change();
        });
    }
};
