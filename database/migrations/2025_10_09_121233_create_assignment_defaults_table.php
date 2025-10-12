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
        Schema::create('assignment_defaults', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->unique();
            $table->unsignedBigInteger('default_user_id')->nullable();
            $table->unsignedBigInteger('default_team_id')->nullable();
            $table->boolean('round_robin_enabled')->default(false);
            $table->boolean('enable_automatic_assignment')->default(true);
            $table->timestamps();

            // Indexes for performance
            $table->index('tenant_id');
            
            // Foreign key constraints
            $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('default_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_defaults');
    }
};
