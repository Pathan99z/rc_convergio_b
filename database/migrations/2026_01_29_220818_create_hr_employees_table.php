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
        if (!Schema::hasTable('hr_employees')) {
            Schema::create('hr_employees', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('team_id')->nullable()->index();
                $table->string('employee_id')->unique();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('first_name');
                $table->string('last_name');
                $table->string('id_number')->nullable();
                $table->string('passport_number')->nullable();
                $table->string('work_email')->unique();
                $table->string('personal_email')->nullable();
                $table->string('phone_number');
                $table->string('job_title');
                $table->string('department');
                $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'intern'])->default('full_time');
                $table->enum('employment_status', ['onboarding', 'active', 'on_leave', 'suspended', 'offboarded'])->default('onboarding');
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->unsignedBigInteger('manager_id')->nullable()->index();
                $table->text('salary')->nullable(); // Encrypted
                $table->text('bank_account')->nullable(); // Encrypted
                $table->json('address')->nullable();
                $table->json('emergency_contact')->nullable();
                $table->unsignedBigInteger('created_by');
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                // Foreign key constraints
                $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('team_id')->references('id')->on('teams')->onDelete('set null');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('manager_id')->references('id')->on('hr_employees')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');

                // Indexes for performance
                $table->index(['tenant_id', 'employment_status']);
                $table->index(['tenant_id', 'department']);
                $table->index(['manager_id', 'employment_status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_employees');
    }
};

