<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority')->default('medium'); // low, medium, high, urgent
            $table->string('status')->default('pending'); // pending, in_progress, completed, cancelled
            $table->timestamp('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('tags')->nullable();
            $table->unsignedBigInteger('owner_id');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('tenant_id');
            $table->morphs('related'); // polymorphic relationship (contact, company, deal, etc.)
            $table->softDeletes();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('owner_id');
            $table->index('assigned_to');
            $table->index('priority');
            $table->index('status');
            $table->index('due_date');
            $table->index('completed_at');

            $table->foreign('owner_id')->references('id')->on('users');
            $table->foreign('assigned_to')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
