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
            if (!Schema::hasColumn('contacts', 'unsubscribed')) {
                $table->boolean('unsubscribed')->default(false)->after('email');
            }
            if (!Schema::hasColumn('contacts', 'unsubscribed_at')) {
                $table->timestamp('unsubscribed_at')->nullable()->after('unsubscribed');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (Schema::hasColumn('contacts', 'unsubscribed')) {
                $table->dropColumn('unsubscribed');
            }
            if (Schema::hasColumn('contacts', 'unsubscribed_at')) {
                $table->dropColumn('unsubscribed_at');
            }
        });
    }
};
