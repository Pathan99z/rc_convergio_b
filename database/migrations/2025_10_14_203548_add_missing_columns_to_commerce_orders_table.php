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
        Schema::table('commerce_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('commerce_orders', 'customer_snapshot')) {
                $table->json('customer_snapshot')->nullable();
            }
            if (!Schema::hasColumn('commerce_orders', 'subtotal')) {
                $table->decimal('subtotal', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('commerce_orders', 'tax')) {
                $table->decimal('tax', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('commerce_orders', 'discount')) {
                $table->decimal('discount', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('commerce_orders', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('commerce_orders', 'status')) {
                $table->enum('status', ['pending', 'paid', 'failed', 'refunded', 'cancelled'])->default('pending')->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commerce_orders', function (Blueprint $table) {
            if (Schema::hasColumn('commerce_orders', 'customer_snapshot')) {
                $table->dropColumn('customer_snapshot');
            }
            if (Schema::hasColumn('commerce_orders', 'subtotal')) {
                $table->dropColumn('subtotal');
            }
            if (Schema::hasColumn('commerce_orders', 'tax')) {
                $table->dropColumn('tax');
            }
            if (Schema::hasColumn('commerce_orders', 'discount')) {
                $table->dropColumn('discount');
            }
            if (Schema::hasColumn('commerce_orders', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }
        });
    }
};
