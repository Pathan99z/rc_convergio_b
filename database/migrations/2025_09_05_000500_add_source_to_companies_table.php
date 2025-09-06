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
        if (!Schema::hasColumn('companies', 'source')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->string('source')->nullable()->after('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('companies', 'source')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('source');
            });
        }
    }
};


