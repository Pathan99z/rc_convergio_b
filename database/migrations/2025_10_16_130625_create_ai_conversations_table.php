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
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('user_email')->nullable();
            $table->text('message');
            $table->json('suggestions');
            $table->decimal('confidence', 5, 2)->default(0.00);
            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'user_email']);
            $table->index(['tenant_id', 'confidence']);

            // Foreign key constraints
            $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_conversations');
    }
};