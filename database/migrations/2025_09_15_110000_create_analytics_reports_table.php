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
        Schema::create('analytics_reports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type')->default('scheduled'); // scheduled, on_demand, export
            $table->json('config')->nullable(); // Report configuration (filters, modules, etc.)
            $table->json('schedule')->nullable(); // Cron schedule or frequency
            $table->string('format')->default('json'); // json, csv, excel, pdf
            $table->string('status')->default('active'); // active, paused, completed, failed
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->json('last_result')->nullable(); // Last report results
            $table->text('error_message')->nullable();
            $table->integer('run_count')->default(0);
            $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            // Indexes
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'type']);
            $table->index(['next_run_at']);
            $table->index(['status', 'next_run_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_reports');
    }
};

