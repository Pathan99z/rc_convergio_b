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
        if (!Schema::hasTable('hr_employee_id_sequence')) {
            Schema::create('hr_employee_id_sequence', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->integer('year');
                $table->integer('last_sequence')->default(0);
                $table->timestamps();

                // Foreign key constraints
                $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');

                // Unique constraint
                $table->unique(['tenant_id', 'year']);

                // Indexes
                $table->index(['tenant_id', 'year']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_employee_id_sequence');
    }
};

