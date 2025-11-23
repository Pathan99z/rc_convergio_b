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
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('from_currency', 3);
            $table->string('to_currency', 3);
            $table->decimal('rate', 10, 6);
            $table->date('effective_date');
            $table->string('source', 50)->default('api')->comment('api or manual');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Unique constraint: one rate per currency pair per day
            $table->unique(['from_currency', 'to_currency', 'effective_date'], 'unique_rate');
            
            // Index for fast lookups
            $table->index(['from_currency', 'to_currency', 'effective_date', 'is_active'], 'idx_rate_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
