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
        Schema::table('contacts', function (Blueprint $table) {
            $table->integer('lead_score')->default(0)->after('tags');
            $table->timestamp('lead_score_updated_at')->nullable()->after('lead_score');
            $table->index('lead_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['lead_score']);
            $table->dropColumn(['lead_score', 'lead_score_updated_at']);
        });
    }
};
