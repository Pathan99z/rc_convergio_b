<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->index();
            $table->string('sender_type'); // 'customer' or 'agent'
            $table->unsignedBigInteger('sender_id')->nullable(); // Agent ID if sender_type is 'agent'
            $table->text('message');
            $table->string('message_type')->default('text'); // text, image, file, system
            $table->json('metadata')->nullable(); // Additional data (file info, etc.)
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('conversation_id')->references('id')->on('live_chat_conversations')->onDelete('cascade');
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('set null');

            // Indexes for performance
            $table->index(['conversation_id', 'created_at']);
            $table->index(['sender_type', 'sender_id']);
            $table->index('is_read');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_chat_messages');
    }
};