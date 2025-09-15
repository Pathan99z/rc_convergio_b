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
        Schema::create('journey_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journey_id')->constrained('journeys')->onDelete('cascade');
            $table->string('step_type'); // send_email, wait, create_task, update_contact, etc.
            $table->json('config'); // Step configuration (delay, template_id, task details, etc.)
            $table->integer('order_no'); // Step execution order
            $table->boolean('is_active')->default(true);
            $table->json('conditions')->nullable(); // Optional step conditions
            $table->timestamps();
            
            // Indexes
            $table->index(['journey_id', 'order_no']);
            $table->index(['journey_id', 'is_active']);
            $table->index('step_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journey_steps');
    }
};
