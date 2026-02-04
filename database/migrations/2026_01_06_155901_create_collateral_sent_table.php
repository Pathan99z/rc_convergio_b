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
        Schema::create('collateral_sent', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->onDelete('cascade');
            $table->foreignId('collateral_id')->constrained('collaterals')->onDelete('cascade');
            $table->foreignId('sent_by')->constrained('users')->onDelete('cascade');
            $table->text('message')->nullable();
            $table->timestamp('sent_at');
            $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['tenant_id', 'contact_id']);
            $table->index(['tenant_id', 'collateral_id']);
            $table->index('sent_at');
            $table->index(['contact_id', 'sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collateral_sent');
    }
};




