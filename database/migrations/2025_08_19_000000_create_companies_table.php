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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('domain')->nullable();
            $table->string('website')->nullable();
            $table->string('industry')->nullable();
            $table->integer('size')->nullable();
            $table->string('type')->nullable();
            $table->json('address')->nullable(); // street, city, state, postal_code, country
            $table->decimal('annual_revenue', 15, 2)->nullable();
            $table->string('timezone')->nullable();
            $table->text('description')->nullable();
            $table->string('linkedin_page')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('tenant_id');
            $table->index('owner_id');
            $table->index('domain');
            $table->index('industry');
            $table->index('type');

            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('owner_id')->references('id')->on('users')->onDelete('set null');

            // Unique constraints
            $table->unique(['domain', 'tenant_id'], 'companies_domain_tenant_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
