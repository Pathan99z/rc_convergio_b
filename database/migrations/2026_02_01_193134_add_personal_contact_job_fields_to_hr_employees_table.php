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
        Schema::table('hr_employees', function (Blueprint $table) {
            // Personal Information Fields
            $table->string('preferred_name')->nullable()->after('last_name');
            $table->date('date_of_birth')->nullable()->after('preferred_name');
            $table->enum('gender', ['male', 'female', 'other'])->nullable()->after('date_of_birth');
            $table->string('nationality')->nullable()->after('gender');
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed'])->nullable()->after('nationality');
            
            // Contact Details Fields
            $table->string('work_phone')->nullable()->after('phone_number');
            $table->text('office_address')->nullable()->after('work_phone');
            
            // Job Information Fields
            $table->string('work_schedule')->nullable()->after('end_date');
            $table->date('probation_end_date')->nullable()->after('work_schedule');
            $table->date('contract_end_date')->nullable()->after('probation_end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropColumn([
                'preferred_name',
                'date_of_birth',
                'gender',
                'nationality',
                'marital_status',
                'work_phone',
                'office_address',
                'work_schedule',
                'probation_end_date',
                'contract_end_date',
            ]);
        });
    }
};
