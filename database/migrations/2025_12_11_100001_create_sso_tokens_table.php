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
        Schema::create('sso_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('jti', 255)->unique(); // JWT ID (unique token identifier)
            $table->timestamp('expires_at'); // Token expiry time
            $table->boolean('used')->default(false); // Has this token been used?
            $table->unsignedBigInteger('source_user_id')->nullable(); // Convergio user ID
            $table->unsignedTinyInteger('product_id')->nullable(); // 1=Console, 2=Future Product, etc.
            $table->timestamps();
            
            // Indexes for performance
            $table->index('jti');
            $table->index(['expires_at', 'used']);
            $table->index('source_user_id');
            $table->index('product_id');
            
            // Foreign key (optional, for cleanup)
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
        Schema::dropIfExists('sso_tokens');
    }
};


