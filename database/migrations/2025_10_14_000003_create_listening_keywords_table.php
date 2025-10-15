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
        Schema::create('listening_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('keyword');
            $table->json('platforms')->nullable(); // Array of platforms to monitor
            $table->timestamp('last_checked_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('mention_count')->default(0); // Total mentions found
            $table->json('settings')->nullable(); // Additional settings like filters, sentiment analysis
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'is_active']);
            $table->index('keyword');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listening_keywords');
    }
};


