<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('value', 15, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('open'); // open, won, lost, closed
            $table->date('expected_close_date')->nullable();
            $table->date('closed_date')->nullable();
            $table->string('close_reason')->nullable();
            $table->json('tags')->nullable();
            $table->unsignedBigInteger('pipeline_id');
            $table->unsignedBigInteger('stage_id');
            $table->unsignedBigInteger('owner_id');
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('tenant_id');
            $table->softDeletes();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('pipeline_id');
            $table->index('stage_id');
            $table->index('owner_id');
            $table->index('contact_id');
            $table->index('company_id');
            $table->index('status');
            $table->index('expected_close_date');

            $table->foreign('pipeline_id')->references('id')->on('pipelines');
            $table->foreign('stage_id')->references('id')->on('stages');
            $table->foreign('owner_id')->references('id')->on('users');
            $table->foreign('contact_id')->references('id')->on('contacts');
            $table->foreign('company_id')->references('id')->on('companies');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
