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
        Schema::create('seo_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('keyword');
            $table->integer('search_volume')->nullable();
            $table->integer('difficulty')->nullable();
            $table->decimal('cpc', 8, 2)->nullable();
            $table->string('competition')->nullable();
            $table->string('target_url')->nullable();
            $table->enum('status', ['tracking', 'paused', 'archived'])->default('tracking');
            $table->timestamps();
            
            $table->index(['user_id', 'keyword']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_keywords');
    }
};
