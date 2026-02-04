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
        if (!Schema::hasTable('hr_announcement_comments')) {
            Schema::create('hr_announcement_comments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('announcement_id');
                $table->unsignedBigInteger('employee_id');
                $table->text('comment');
                $table->timestamps();
                $table->softDeletes();

                // Indexes
                $table->index(['tenant_id', 'announcement_id'], 'idx_comment_tenant_announcement');
                $table->index('employee_id', 'idx_comment_employee');

                // Foreign keys
                $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('announcement_id')->references('id')->on('hr_announcements')->onDelete('cascade');
                $table->foreign('employee_id')->references('id')->on('hr_employees')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_announcement_comments');
    }
};

