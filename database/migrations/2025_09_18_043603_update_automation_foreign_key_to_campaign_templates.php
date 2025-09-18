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
            // Add foreign key constraint pointing to campaign_templates table
            $table->foreign('template_id')->references('id')->on('campaign_templates')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_automations', function (Blueprint $table) {
            // Drop the campaign_templates foreign key
            $table->dropForeign(['template_id']);
        });
    }
};