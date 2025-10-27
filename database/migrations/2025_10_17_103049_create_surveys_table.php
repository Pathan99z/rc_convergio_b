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
        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['csat', 'nps', 'post_ticket', 'general'])->default('general');
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_send')->default(false);
            $table->json('settings')->nullable(); // Additional settings like trigger conditions
            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'is_active']);
            $table->index('created_at');

            // Foreign key constraints
            $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('surveys');
    }
};