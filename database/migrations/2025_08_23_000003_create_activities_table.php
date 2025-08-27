<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table): void {
            $table->id();
            $table->string('type'); // call, email, meeting, note, task
            $table->string('subject');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // store additional data like call duration, email content, etc.
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('status')->default('scheduled'); // scheduled, completed, cancelled
            $table->unsignedBigInteger('owner_id');
            $table->unsignedBigInteger('tenant_id');
            $table->morphs('related'); // polymorphic relationship (contact, company, deal, etc.)
            $table->softDeletes();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('owner_id');
            $table->index('type');
            $table->index('status');
            $table->index('scheduled_at');
            $table->index('completed_at');

            $table->foreign('owner_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
