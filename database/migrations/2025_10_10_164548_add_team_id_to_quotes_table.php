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
        if (!Schema::hasColumn('quotes', 'team_id')) {
            Schema::table('quotes', function (Blueprint $table) {
                $table->unsignedBigInteger('team_id')->nullable()->index();
                $table->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('quotes', 'team_id')) {
            Schema::table('quotes', function (Blueprint $table) {
                $table->dropForeign(['team_id']);
                $table->dropColumn('team_id');
            });
        }
    }
};
