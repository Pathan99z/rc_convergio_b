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
        Schema::table('commerce_payment_links', function (Blueprint $table) {
            if (!Schema::hasColumn('commerce_payment_links', 'order_id')) {
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->foreign('order_id')->references('id')->on('commerce_orders')->onDelete('set null');
            }
            if (!Schema::hasColumn('commerce_payment_links', 'public_url')) {
                $table->string('public_url')->nullable();
            }
            if (!Schema::hasColumn('commerce_payment_links', 'metadata')) {
                $table->json('metadata')->nullable();
            }
            if (!Schema::hasColumn('commerce_payment_links', 'owner_id')) {
                $table->unsignedBigInteger('owner_id')->nullable()->index();
                $table->foreign('owner_id')->references('id')->on('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('commerce_payment_links', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('commerce_payment_links', 'status')) {
                $table->enum('status', ['draft', 'active', 'completed', 'expired', 'cancelled'])->default('draft')->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commerce_payment_links', function (Blueprint $table) {
            if (Schema::hasColumn('commerce_payment_links', 'order_id')) {
                $table->dropForeign(['order_id']);
                $table->dropColumn('order_id');
            }
            if (Schema::hasColumn('commerce_payment_links', 'public_url')) {
                $table->dropColumn('public_url');
            }
            if (Schema::hasColumn('commerce_payment_links', 'metadata')) {
                $table->dropColumn('metadata');
            }
            if (Schema::hasColumn('commerce_payment_links', 'owner_id')) {
                $table->dropForeign(['owner_id']);
                $table->dropColumn('owner_id');
            }
            if (Schema::hasColumn('commerce_payment_links', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }
        });
    }
};
