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
        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('survey_id');
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->string('respondent_email')->nullable();
            $table->json('responses'); // Store all question responses
            $table->decimal('overall_score', 5, 2)->nullable(); // For CSAT/NPS scores
            $table->text('feedback')->nullable(); // Additional feedback
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['survey_id', 'created_at']);
            $table->index(['contact_id', 'created_at']);
            $table->index(['ticket_id', 'created_at']);
            $table->index('respondent_email');
            $table->index('overall_score');

            // Foreign key constraints
            $table->foreign('survey_id')->references('id')->on('surveys')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('set null');
            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('survey_responses');
    }
};