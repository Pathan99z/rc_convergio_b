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
        Schema::create('linked_user_accounts', function (Blueprint $table) {
            $table->id();
            
            // Source (Convergio)
            $table->unsignedBigInteger('source_user_id'); // Convergio user ID
            
            // Target (External Product)
            $table->unsignedInteger('target_user_id'); // External product user ID
            $table->string('target_user_uid')->nullable(); // External product's UID field
            
            // Product Identification
            $table->unsignedTinyInteger('product_id'); // 1=Console, 2=Future Product, etc.
            
            // Integration Type
            $table->unsignedTinyInteger('integration_type'); // 1=SSO, 2=Password Sync, 3=Both, etc.
            
            // Status/Confirmation
            $table->unsignedTinyInteger('status')->default(1); // 1=Active, 2=Pending, 3=Failed, 4=Disabled
            
            // Metadata
            $table->string('target_username')->nullable(); // Store external username
            $table->json('metadata')->nullable(); // Store additional product-specific data
            
            // Timestamps
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->unique(['source_user_id', 'product_id']); // One link per product per user
            $table->index(['product_id', 'status']);
            $table->index('target_user_id');
            $table->index(['source_user_id', 'integration_type']);
            
            // Foreign Key
            $table->foreign('source_user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('linked_user_accounts');
    }
};


