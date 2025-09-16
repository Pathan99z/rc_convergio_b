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
        Schema::create('campaign_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('subject');
            $table->longText('content');
            $table->string('type')->default('email'); // email, sms, etc.
            $table->json('settings')->nullable();
            $table->json('variables')->nullable(); // Template variables like {{name}}, {{company}}
            $table->string('category')->nullable(); // newsletter, promotional, transactional, etc.
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(false); // Can be used by other users
            $table->bigInteger('usage_count')->default(0);
            $table->bigInteger('tenant_id')->unsigned();
            $table->bigInteger('created_by')->unsigned();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'category']);
            $table->index(['tenant_id', 'is_public']);
            $table->index('usage_count');

            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_templates');
    }
};

