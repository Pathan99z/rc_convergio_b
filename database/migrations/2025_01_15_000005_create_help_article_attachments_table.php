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
        Schema::create('help_article_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('article_id');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('filename');
            $table->unsignedBigInteger('size');
            $table->string('mime_type');
            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'article_id']);
            $table->index(['article_id', 'created_at']);

            // Foreign key constraints
            $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('article_id')->references('id')->on('help_articles')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('help_article_attachments');
    }
};
