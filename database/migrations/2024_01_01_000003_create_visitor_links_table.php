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
        Schema::create('visitor_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('visitor_id');
            $table->unsignedBigInteger('contact_id');
            $table->timestamp('linked_at')->nullable();
            $table->timestamps();

            $table->unique(['visitor_id', 'contact_id']);
            $table->index(['visitor_id', 'linked_at']);
            $table->index(['contact_id', 'linked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitor_links');
    }
};
