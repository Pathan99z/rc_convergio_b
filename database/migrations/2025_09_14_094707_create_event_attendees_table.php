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
        Schema::create('event_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->foreignId('contact_id')->constrained('contacts')->onDelete('cascade');
            $table->enum('rsvp_status', ['going', 'interested', 'declined'])->default('interested');
            $table->boolean('attended')->default(false);
            $table->timestamp('rsvp_at')->nullable();
            $table->timestamp('attended_at')->nullable();
            $table->json('metadata')->nullable(); // Additional attendee data
            $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            // Indexes
            $table->index(['event_id', 'rsvp_status']);
            $table->index(['contact_id', 'tenant_id']);
            $table->index(['tenant_id', 'attended']);
            $table->unique(['event_id', 'contact_id']); // Prevent duplicate RSVPs
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_attendees');
    }
};
