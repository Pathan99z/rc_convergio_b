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
        Schema::create('sequence_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sequence_id');
            $table->integer('step_order');
            $table->enum('action_type', ['email', 'task', 'wait']);
            $table->integer('delay_hours')->default(0);
            $table->unsignedBigInteger('email_template_id')->nullable();
            $table->string('task_title')->nullable();
            $table->text('task_description')->nullable();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index('sequence_id');
            $table->index('tenant_id');
            $table->index(['sequence_id', 'step_order']);

            // Unique constraint
            $table->unique(['sequence_id', 'step_order']);

            // Foreign key constraints
            $table->foreign('sequence_id')->references('id')->on('sequences')->onDelete('cascade');
            // Note: email_template_id foreign key removed as email_templates table doesn't exist
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
        Schema::dropIfExists('sequence_steps');
    }
};
