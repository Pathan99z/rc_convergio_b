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
        if (!Schema::hasTable('hr_kpi_review_items')) {
            Schema::create('hr_kpi_review_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('kpi_review_id')->index();
                $table->unsignedBigInteger('kpi_template_item_id')->index();
                $table->decimal('score', 4, 2)->nullable(); // 0-10, e.g., 8.50
                $table->text('comments')->nullable(); // Individual KPI comments
                $table->timestamps();

                // Foreign key constraints
                $table->foreign('kpi_review_id')
                    ->references('id')
                    ->on('hr_kpi_reviews')
                    ->onDelete('cascade');
                $table->foreign('kpi_template_item_id')
                    ->references('id')
                    ->on('hr_kpi_template_items')
                    ->onDelete('cascade');

                // Unique constraint: One review item per KPI per review
                $table->unique(['kpi_review_id', 'kpi_template_item_id'], 'unique_review_template_item');

                // Note: Indexes are already created above with ->index() on column definitions
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_kpi_review_items');
    }
};

