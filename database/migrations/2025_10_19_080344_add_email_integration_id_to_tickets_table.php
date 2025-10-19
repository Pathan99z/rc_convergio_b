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
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('email_integration_id')->nullable()->after('team_id');
            $table->foreign('email_integration_id')->references('id')->on('email_integrations')->onDelete('set null');
            $table->index('email_integration_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['email_integration_id']);
            $table->dropIndex(['email_integration_id']);
            $table->dropColumn('email_integration_id');
        });
    }
};