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
        if (Schema::hasTable('commerce_settings')) {
            Schema::table('commerce_settings', function (Blueprint $table) {
                // Add payment gateway selection
                $table->string('payment_gateway')->default('stripe')->after('tenant_id');
                
                // Add PayFast fields
                $table->string('payfast_merchant_id')->nullable()->after('stripe_secret_key');
                $table->string('payfast_merchant_key')->nullable()->after('payfast_merchant_id');
                $table->string('payfast_passphrase')->nullable()->after('payfast_merchant_key');
                $table->string('payfast_webhook_secret')->nullable()->after('payfast_passphrase');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('commerce_settings')) {
            Schema::table('commerce_settings', function (Blueprint $table) {
                $table->dropColumn([
                    'payment_gateway',
                    'payfast_merchant_id',
                    'payfast_merchant_key',
                    'payfast_passphrase',
                    'payfast_webhook_secret',
                ]);
            });
        }
    }
};


