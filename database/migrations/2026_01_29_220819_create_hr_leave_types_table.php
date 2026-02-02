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
        if (!Schema::hasTable('hr_leave_types')) {
            Schema::create('hr_leave_types', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('name');
                $table->string('code', 10)->unique();
                $table->boolean('accrues_monthly')->default(false);
                $table->decimal('max_balance', 8, 2)->nullable();
                $table->boolean('carry_forward')->default(false);
                $table->boolean('requires_approval')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                // Foreign key constraints
                $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');

                // Indexes
                $table->index(['tenant_id', 'is_active']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_leave_types');
    }
};

