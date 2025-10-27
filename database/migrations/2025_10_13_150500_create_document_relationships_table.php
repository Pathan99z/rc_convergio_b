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
        Schema::create('document_relationships', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->string('related_type'); // 'App\Models\Contact', 'App\Models\Deal', etc.
            $table->unsignedBigInteger('related_id');
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
            $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');

            // Indexes for performance
            $table->index(['related_type', 'related_id']);
            $table->index(['document_id', 'related_type', 'related_id']);
            
            // Ensure unique relationships
            $table->unique(['document_id', 'related_type', 'related_id'], 'unique_document_relationship');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_relationships');
    }
};
