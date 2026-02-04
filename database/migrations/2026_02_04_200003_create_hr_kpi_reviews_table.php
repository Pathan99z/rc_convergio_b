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
        if (!Schema::hasTable('hr_kpi_reviews')) {
            Schema::create('hr_kpi_reviews', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('kpi_assignment_id')->index();
                $table->enum('review_type', ['self_review', 'manager_review']);
                $table->unsignedBigInteger('reviewed_by'); // user_id (employee or manager)
                $table->decimal('final_score', 5, 2)->nullable(); // 0-10, calculated weighted average
                $table->enum('grade', ['A', 'B', 'C', 'D'])->nullable(); // A: 90-100, B: 75-89, C: 60-74, D: <60
                $table->text('comments')->nullable(); // Overall comments/remarks
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();

                // Foreign key constraints
                $table->foreign('kpi_assignment_id')
                    ->references('id')
                    ->on('hr_kpi_assignments')
                    ->onDelete('cascade');
                $table->foreign('reviewed_by')
                    ->references('id')
                    ->on('users')
                    ->onDelete('cascade');

                // Unique constraint: One review per type per assignment
                $table->unique(['kpi_assignment_id', 'review_type'], 'unique_assignment_review_type');

                // Indexes
                $table->index(['kpi_assignment_id', 'review_type']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_kpi_reviews');
    }
};

