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
        if (!Schema::hasTable('commerce_order_items')) {
            Schema::create('commerce_order_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('name');
                $table->integer('quantity')->default(1);
                $table->decimal('unit_price', 15, 2);
                $table->decimal('discount', 15, 2)->default(0);
                $table->decimal('tax_rate', 5, 2)->default(0);
                $table->decimal('subtotal', 15, 2);
                $table->timestamps();

                // Foreign key constraints
                $table->foreign('order_id')->references('id')->on('commerce_orders')->onDelete('cascade');
                $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');

                // Indexes for performance
                $table->index(['order_id']);
                $table->index(['product_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commerce_order_items');
    }
};
