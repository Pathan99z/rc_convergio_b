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
        // Drop the email_templates table if it exists
        Schema::dropIfExists('email_templates');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate email_templates table if needed
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subject');
            $table->longText('content');
            $table->text('description')->nullable();
            $table->enum('type', ['welcome', 'follow_up', 'sales', 'thank_you', 'custom'])->default('custom');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
            $table->index(['type', 'tenant_id']);
            $table->index('created_by');

            $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }
};