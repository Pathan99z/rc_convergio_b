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
        if (!Schema::hasTable('commerce_order_invoice_items')) {
            Schema::create('commerce_order_invoice_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('invoice_id');
                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('name');
                $table->text('description')->nullable();
                $table->integer('quantity')->default(1);
                $table->decimal('unit_price', 15, 2);
                $table->decimal('discount', 15, 2)->default(0);
                $table->decimal('tax_rate', 5, 2)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('line_total', 15, 2);
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                // Indexes
                $table->index(['invoice_id']);
                $table->index(['product_id']);

                // Foreign keys
                $table->foreign('invoice_id')->references('id')->on('commerce_order_invoices')->onDelete('cascade');
                $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('commerce_order_invoice_items')) {
            Schema::dropIfExists('commerce_order_invoice_items');
        }
    }
};
