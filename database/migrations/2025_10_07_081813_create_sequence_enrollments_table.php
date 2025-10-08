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
        Schema::create('sequence_enrollments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sequence_id');
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->enum('status', ['active', 'paused', 'completed', 'cancelled'])->default('active');
            $table->integer('current_step')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index('tenant_id');
            $table->index('sequence_id');
            $table->index(['target_type', 'target_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['sequence_id', 'status']);

            // Foreign key constraints
            $table->foreign('sequence_id')->references('id')->on('sequences')->onDelete('cascade');
            $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sequence_enrollments');
    }
};
