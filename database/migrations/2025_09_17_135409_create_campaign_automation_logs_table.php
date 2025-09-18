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
        Schema::create('campaign_automation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('automation_id');
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->string('status')->default('pending'); // pending, success, failed
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('tenant_id');
            $table->timestamps();

            $table->index(['automation_id', 'contact_id']);
            $table->index(['automation_id', 'status']);
            $table->index(['contact_id', 'executed_at']);
            $table->index(['tenant_id', 'status']);
            $table->index('executed_at');

            $table->foreign('automation_id')->references('id')->on('campaign_automations')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_automation_logs');
    }
};
