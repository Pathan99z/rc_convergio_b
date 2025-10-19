<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('session_id')->index(); // Unique session identifier
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('status')->default('active'); // active, closed, waiting
            $table->unsignedBigInteger('assigned_agent_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->integer('message_count')->default(0);
            $table->text('notes')->nullable(); // Agent notes
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('assigned_agent_id')->references('id')->on('users')->onDelete('set null');

            // Indexes for performance
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'assigned_agent_id']);
            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_chat_conversations');
    }
};