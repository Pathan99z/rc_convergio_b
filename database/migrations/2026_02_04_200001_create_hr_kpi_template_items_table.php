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
        if (!Schema::hasTable('hr_kpi_template_items')) {
            Schema::create('hr_kpi_template_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('kpi_template_id')->index();
                $table->string('name'); // e.g., "Code Quality & Standards", "Revenue Generation"
                $table->decimal('weight', 5, 2)->default(0); // 0-100, e.g., 30.00 for 30%
                $table->text('description')->nullable(); // Target/description
                $table->integer('order')->default(0); // Display order
                $table->timestamps();

                // Foreign key constraints
                $table->foreign('kpi_template_id')
                    ->references('id')
                    ->on('hr_kpi_templates')
                    ->onDelete('cascade');

                // Indexes
                $table->index(['kpi_template_id', 'order']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_kpi_template_items');
    }
};

