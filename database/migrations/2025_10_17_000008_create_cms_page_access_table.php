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
        Schema::create('cms_page_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('cms_pages')->onDelete('cascade');
            $table->string('access_type'); // 'public', 'members', 'role', 'custom'
            $table->json('access_rules')->nullable(); // Specific rules (roles, user IDs, etc.)
            $table->boolean('require_login')->default(false);
            $table->json('allowed_roles')->nullable(); // Array of role names
            $table->json('allowed_users')->nullable(); // Array of user IDs
            $table->timestamp('access_from')->nullable(); // Time-based access
            $table->timestamp('access_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index(['page_id', 'access_type']);
            $table->index(['is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_page_access');
    }
};



