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
        if (!Schema::hasTable('mail_settings')) {
            Schema::create('mail_settings', function (Blueprint $table) {
                $table->id();
                $table->string('setting_key', 100)->index();
                $table->text('setting_value')->nullable();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->timestamps();

                // Composite unique index to prevent duplicate settings per tenant
                $table->unique(['setting_key', 'tenant_id'], 'mail_settings_key_tenant_unique');

                // Foreign key constraint
                $table->foreign('tenant_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('cascade');

                // Additional indexes for performance
                $table->index(['tenant_id', 'setting_key'], 'mail_settings_tenant_key_idx');
                $table->index('created_at', 'mail_settings_created_at_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_settings');
    }
};

