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
        Schema::create('assignment_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('priority')->default(100);
            $table->json('criteria'); // JSON conditions for rule matching
            $table->json('action'); // JSON action to take when rule matches
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            // Indexes for performance
            $table->index(['tenant_id', 'active', 'priority']);
            $table->index(['tenant_id', 'created_by']);
            
            // Foreign key constraints
            $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_rules');
    }
};
