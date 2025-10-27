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
        Schema::table('commerce_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('commerce_transactions', 'transaction_type')) {
                $table->string('transaction_type')->nullable()->after('event_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commerce_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('commerce_transactions', 'transaction_type')) {
                $table->dropColumn('transaction_type');
            }
        });
    }
};