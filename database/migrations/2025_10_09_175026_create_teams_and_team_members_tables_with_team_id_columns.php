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
        // Create teams table
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            // Add foreign key constraint for created_by
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            
            // Add index for tenant_id for performance
            $table->index(['tenant_id', 'name']);
        });

        // Create team_members table
        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('role', ['manager', 'member'])->default('member');
            $table->timestamps();

            // Add foreign key constraints
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            // Add unique constraint to prevent duplicate memberships
            $table->unique(['team_id', 'user_id']);
            
            // Add indexes for performance
            $table->index(['team_id', 'role']);
            $table->index('user_id');
        });

        // Add team_id column to users table
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable()->after('tenant_id');
            $table->index('team_id');
        });

        // Add team_id column to contacts table
        Schema::table('contacts', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable()->after('tenant_id');
            $table->index('team_id');
        });

        // Add team_id column to deals table
        Schema::table('deals', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable()->after('tenant_id');
            $table->index('team_id');
        });

        // Add team_id column to companies table
        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable()->after('tenant_id');
            $table->index('team_id');
        });

        // Add team_id column to tasks table
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable()->after('tenant_id');
            $table->index('team_id');
        });

        // Add team_id column to activities table
        Schema::table('activities', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable()->after('tenant_id');
            $table->index('team_id');
        });

        // Add foreign key constraints for team_id columns after all tables exist
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('set null');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('set null');
        });

        Schema::table('deals', function (Blueprint $table) {
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('set null');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('set null');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('set null');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign key constraints first
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropIndex(['team_id']);
            $table->dropColumn('team_id');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropIndex(['team_id']);
            $table->dropColumn('team_id');
        });

        Schema::table('deals', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropIndex(['team_id']);
            $table->dropColumn('team_id');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropIndex(['team_id']);
            $table->dropColumn('team_id');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropIndex(['team_id']);
            $table->dropColumn('team_id');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropIndex(['team_id']);
            $table->dropColumn('team_id');
        });

        // Drop team_members table
        Schema::dropIfExists('team_members');

        // Drop teams table
        Schema::dropIfExists('teams');
    }
};