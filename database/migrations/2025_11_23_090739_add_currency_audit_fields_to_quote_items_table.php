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
        Schema::table('quote_items', function (Blueprint $table) {
            $table->decimal('original_unit_price', 15, 2)->nullable()->after('unit_price');
            $table->string('original_currency', 3)->nullable()->after('original_unit_price');
            $table->decimal('exchange_rate_used', 10, 6)->nullable()->after('original_currency');
            $table->timestamp('converted_at')->nullable()->after('exchange_rate_used');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quote_items', function (Blueprint $table) {
            $table->dropColumn(['original_unit_price', 'original_currency', 'exchange_rate_used', 'converted_at']);
        });
    }
};
