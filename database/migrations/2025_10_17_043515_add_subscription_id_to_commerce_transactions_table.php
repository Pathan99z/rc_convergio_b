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
        Schema::table('commerce_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('commerce_transactions', 'subscription_id')) {
                $table->unsignedBigInteger('subscription_id')->nullable()->after('payment_link_id');
                $table->foreign('subscription_id')->references('id')->on('commerce_subscriptions')->onDelete('set null');
                $table->index(['subscription_id']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commerce_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('commerce_transactions', 'subscription_id')) {
                $table->dropForeign(['subscription_id']);
                $table->dropIndex(['subscription_id']);
                $table->dropColumn('subscription_id');
            }
        });
    }
};
