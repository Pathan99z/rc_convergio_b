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
        Schema::create('sequence_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('enrollment_id');
            $table->unsignedBigInteger('step_id');
            $table->string('action_performed');
            $table->timestamp('performed_at');
            $table->enum('status', ['success', 'failed', 'skipped']);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            // Indexes
            $table->index('enrollment_id');
            $table->index('step_id');
            $table->index('tenant_id');
            $table->index(['enrollment_id', 'performed_at']);
            $table->index(['tenant_id', 'status']);

            // Foreign key constraints
            $table->foreign('enrollment_id')->references('id')->on('sequence_enrollments')->onDelete('cascade');
            $table->foreign('step_id')->references('id')->on('sequence_steps')->onDelete('cascade');
            $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sequence_logs');
    }
};
