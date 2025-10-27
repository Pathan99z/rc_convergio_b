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
        Schema::create('assignment_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('record_type'); // 'contact' or 'deal'
            $table->unsignedBigInteger('record_id');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('rule_id')->nullable();
            $table->string('assignment_type')->nullable(); // 'rule', 'default', 'round_robin'
            $table->json('details')->nullable(); // Additional context about the assignment
            $table->timestamps();

            // Indexes for performance and reporting
            $table->index(['tenant_id', 'record_type', 'record_id']);
            $table->index(['tenant_id', 'assigned_to']);
            $table->index(['tenant_id', 'rule_id']);
            $table->index(['tenant_id', 'created_at']);
            
            // Foreign key constraints
            $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->foreign('rule_id')->references('id')->on('assignment_rules')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_audits');
    }
};
