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
        Schema::create('deal_stage_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('deal_id');
            $table->unsignedBigInteger('from_stage_id')->nullable(); // null for initial stage
            $table->unsignedBigInteger('to_stage_id');
            $table->text('reason'); // REQUIRED - no nullable
            $table->unsignedBigInteger('moved_by'); // user_id
            $table->unsignedBigInteger('tenant_id');
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['deal_id', 'created_at']); // For deal timeline queries
            $table->index(['tenant_id', 'created_at']); // For tenant queries
            $table->index('to_stage_id'); // For stage analytics
            
            // Foreign keys
            $table->foreign('deal_id')->references('id')->on('deals')->onDelete('cascade');
            $table->foreign('from_stage_id')->references('id')->on('stages')->onDelete('set null');
            $table->foreign('to_stage_id')->references('id')->on('stages')->onDelete('cascade');
            $table->foreign('moved_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deal_stage_history');
    }
};
