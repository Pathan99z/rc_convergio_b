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
        Schema::create('seo_report_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_report_id')->constrained()->onDelete('cascade');
            $table->foreignId('seo_keyword_id')->constrained()->onDelete('cascade');
            $table->integer('frequency')->default(1);
            $table->string('source'); // 'title', 'meta', 'heading'
            $table->timestamps();
            
            $table->unique(['seo_report_id', 'seo_keyword_id', 'source']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_report_keywords');
    }
};
