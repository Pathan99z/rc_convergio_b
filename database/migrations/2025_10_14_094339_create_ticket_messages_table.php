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
        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('author_id');
            $table->string('author_type')->default('App\Models\User');
            $table->text('body');
            $table->enum('type', ['public', 'internal'])->default('public');
            $table->enum('direction', ['inbound', 'outbound'])->default('outbound');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade');
            $table->foreign('author_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes for performance
            $table->index('ticket_id');
            $table->index('author_id');
            $table->index('author_type');
            $table->index('type');
            $table->index('direction');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_messages');
    }
};
