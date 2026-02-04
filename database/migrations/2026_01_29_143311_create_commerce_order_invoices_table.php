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
        if (!Schema::hasTable('commerce_order_invoices')) {
            Schema::create('commerce_order_invoices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('quote_id')->nullable();
                $table->unsignedBigInteger('order_id')->nullable();
                $table->unsignedBigInteger('contact_id')->nullable();
                $table->unsignedBigInteger('deal_id')->nullable();
                $table->string('invoice_number')->unique();
                $table->date('invoice_date');
                $table->date('due_date');
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('tax', 15, 2)->default(0);
                $table->decimal('discount', 15, 2)->default(0);
                $table->decimal('total', 15, 2)->default(0);
                $table->string('currency', 3)->default('ZAR');
                $table->enum('status', ['paid', 'open', 'void', 'draft'])->default('paid');
                $table->string('payment_method')->nullable();
                $table->string('payment_reference')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->string('pdf_path')->nullable();
                $table->text('notes')->nullable();
                $table->json('raw_payload')->nullable();
                $table->timestamps();
                $table->softDeletes();

                // Indexes
                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'invoice_number']);
                $table->index(['quote_id']);
                $table->index(['order_id']);
                $table->index(['contact_id']);
                $table->index(['deal_id']);

                // Foreign keys
                $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('quote_id')->references('id')->on('quotes')->onDelete('set null');
                $table->foreign('order_id')->references('id')->on('commerce_orders')->onDelete('set null');
                $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('set null');
                $table->foreign('deal_id')->references('id')->on('deals')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('commerce_order_invoices')) {
            Schema::dropIfExists('commerce_order_invoices');
        }
    }
};


