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
        Schema::create('intent_actions_daily', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->date('day');
            $table->string('action', 100);
            $table->integer('count')->default(0);
            $table->decimal('avg_score', 5, 2)->default(0);
            $table->integer('max_score')->default(0);
            $table->integer('min_score')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'day', 'action']);
            $table->index(['tenant_id', 'day']);
            $table->index(['tenant_id', 'day', 'count']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('intent_actions_daily');
    }
};
