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
        Schema::table('quotes', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false)->after('status');
            $table->enum('quote_type', ['primary', 'follow-up', 'renewal', 'amendment', 'alternative'])->default('primary')->after('is_primary');
            $table->index(['deal_id', 'is_primary']);
            $table->index(['deal_id', 'quote_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropIndex(['deal_id', 'is_primary']);
            $table->dropIndex(['deal_id', 'quote_type']);
            $table->dropColumn(['is_primary', 'quote_type']);
        });
    }
};