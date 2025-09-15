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
        Schema::create('campaign_automations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->onDelete('cascade');
            $table->string('trigger_event'); // form_submitted, segment_joined, etc.
            $table->integer('delay_minutes')->default(0);
            $table->string('action')->default('send_email'); // send_email, etc.
            $table->json('metadata')->nullable(); // JSON for configs
            $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['campaign_id', 'trigger_event']);
            $table->index(['tenant_id', 'trigger_event']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_automations');
    }
};
