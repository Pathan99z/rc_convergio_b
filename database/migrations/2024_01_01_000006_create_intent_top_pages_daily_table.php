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
        Schema::create('intent_top_pages_daily', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->date('day');
            $table->string('page_url', 500);
            $table->integer('visits')->default(0);
            $table->decimal('avg_score', 5, 2)->default(0);
            $table->integer('max_score')->default(0);
            $table->integer('min_score')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'day', 'page_url']);
            $table->index(['tenant_id', 'day']);
            $table->index(['tenant_id', 'day', 'visits']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('intent_top_pages_daily');
    }
};
