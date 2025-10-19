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
        if (!Schema::hasTable('tenant_branding')) {
            Schema::create('tenant_branding', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->unique();
                $table->string('company_name')->nullable();
                $table->string('logo_url')->nullable();
                $table->string('primary_color', 7)->default('#3b82f6');
                $table->string('secondary_color', 7)->default('#1f2937');
                $table->text('company_address')->nullable();
                $table->string('company_phone', 50)->nullable();
                $table->string('company_email')->nullable();
                $table->string('company_website')->nullable();
                $table->text('invoice_footer')->nullable();
                $table->text('email_signature')->nullable();
                $table->json('custom_fields')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();

                // Foreign key constraint
                $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
                
                // Indexes
                $table->index(['tenant_id', 'active']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_branding');
    }
};
