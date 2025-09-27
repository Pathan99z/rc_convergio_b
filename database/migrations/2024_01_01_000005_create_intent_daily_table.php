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
        Schema::create('intent_daily', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->date('day');
            $table->integer('total_events')->default(0);
            $table->integer('unique_contacts')->default(0);
            $table->integer('unique_companies')->default(0);
            $table->decimal('avg_score', 5, 2)->default(0);
            $table->integer('high_events')->default(0); // score >= 60
            $table->integer('medium_events')->default(0); // score 40-59
            $table->integer('low_events')->default(0); // score < 40
            $table->integer('very_high_events')->default(0); // score >= 80
            $table->integer('very_low_events')->default(0); // score < 20
            $table->integer('max_score')->default(0);
            $table->integer('min_score')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'day']);
            $table->index(['tenant_id', 'day']);
            $table->index('day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('intent_daily');
    }
};
