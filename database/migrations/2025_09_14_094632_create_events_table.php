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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['webinar', 'demo', 'workshop', 'conference', 'meeting'])->default('webinar');
            $table->timestamp('scheduled_at');
            $table->string('location')->nullable(); // Physical location or virtual meeting link
            $table->json('settings')->nullable(); // Additional event settings
            $table->boolean('is_active')->default(true);
            $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            // Indexes
            $table->index(['tenant_id', 'scheduled_at']);
            $table->index(['type', 'is_active']);
            $table->index('scheduled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
