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
        Schema::create('cms_ab_test_visitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ab_test_id')->constrained('cms_ab_tests')->onDelete('cascade');
            $table->string('visitor_id'); // Session ID or user ID
            $table->enum('variant_shown', ['a', 'b']); // Which variant was shown
            $table->boolean('converted')->default(false); // Did they complete the goal?
            $table->json('conversion_data')->nullable(); // What action they took
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('referrer')->nullable();
            $table->timestamp('visited_at');
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['ab_test_id', 'variant_shown']);
            $table->index(['visitor_id']);
            $table->index(['converted', 'converted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_ab_test_visitors');
    }
};



