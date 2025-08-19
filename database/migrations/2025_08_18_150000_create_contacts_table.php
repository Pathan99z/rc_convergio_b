<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->unsignedBigInteger('owner_id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('lifecycle_stage')->nullable();
            $table->string('source')->nullable();
            $table->json('tags')->nullable();
            $table->unsignedBigInteger('tenant_id');
            $table->softDeletes();
            $table->timestamps();

            $table->index('email');
            $table->index('phone');
            $table->index('tenant_id');
            $table->index('owner_id');

            // Optional FKs if companies/users tables exist
            // $table->foreign('owner_id')->references('id')->on('users');
            // $table->foreign('company_id')->references('id')->on('companies');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};


