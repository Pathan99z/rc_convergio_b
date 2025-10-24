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
        // Add performance indexes for speed optimization
        // These indexes will significantly improve query performance without affecting functionality
        
        // Contacts table optimization
        if (Schema::hasTable('contacts')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->index(['tenant_id', 'email'], 'idx_contacts_tenant_email');
                $table->index(['tenant_id', 'lifecycle_stage'], 'idx_contacts_tenant_lifecycle');
                $table->index(['tenant_id', 'created_at'], 'idx_contacts_tenant_created');
                $table->index(['tenant_id', 'owner_id'], 'idx_contacts_tenant_owner');
            });
        }

        // Deals table optimization
        if (Schema::hasTable('deals')) {
            Schema::table('deals', function (Blueprint $table) {
                $table->index(['tenant_id', 'owner_id'], 'idx_deals_tenant_owner');
                $table->index(['tenant_id', 'status'], 'idx_deals_tenant_status');
                $table->index(['tenant_id', 'pipeline_id'], 'idx_deals_tenant_pipeline');
                $table->index(['tenant_id', 'created_at'], 'idx_deals_tenant_created');
            });
        }

        // Activities table optimization
        if (Schema::hasTable('activities')) {
            Schema::table('activities', function (Blueprint $table) {
                $table->index(['tenant_id', 'owner_id'], 'idx_activities_tenant_owner');
                $table->index(['tenant_id', 'scheduled_at'], 'idx_activities_tenant_scheduled');
                $table->index(['tenant_id', 'created_at'], 'idx_activities_tenant_created');
            });
        }

        // Campaigns table optimization
        if (Schema::hasTable('campaigns')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->index(['tenant_id', 'status'], 'idx_campaigns_tenant_status');
                $table->index(['tenant_id', 'created_at'], 'idx_campaigns_tenant_created');
            });
        }

        // Campaign recipients optimization
        if (Schema::hasTable('campaign_recipients')) {
            Schema::table('campaign_recipients', function (Blueprint $table) {
                $table->index(['tenant_id'], 'idx_campaign_recipients_tenant');
                $table->index(['campaign_id', 'status'], 'idx_campaign_recipients_campaign_status');
            });
        }

        // Assignment rules optimization
        if (Schema::hasTable('assignment_rules')) {
            Schema::table('assignment_rules', function (Blueprint $table) {
                $table->index(['tenant_id', 'active'], 'idx_assignment_rules_tenant_active');
                $table->index(['tenant_id', 'priority'], 'idx_assignment_rules_tenant_priority');
            });
        }

        // Tasks table optimization
        if (Schema::hasTable('tasks')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->index(['tenant_id', 'owner_id'], 'idx_tasks_tenant_owner');
                $table->index(['tenant_id', 'status'], 'idx_tasks_tenant_status');
                $table->index(['tenant_id', 'due_date'], 'idx_tasks_tenant_due_date');
            });
        }

        // Companies table optimization
        if (Schema::hasTable('companies')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->index(['tenant_id', 'created_at'], 'idx_companies_tenant_created');
                $table->index(['tenant_id', 'industry'], 'idx_companies_tenant_industry');
            });
        }

        // Users table optimization
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['tenant_id'], 'idx_users_tenant');
                $table->index(['team_id'], 'idx_users_team');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove performance indexes
        if (Schema::hasTable('contacts')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->dropIndex('idx_contacts_tenant_email');
                $table->dropIndex('idx_contacts_tenant_lifecycle');
                $table->dropIndex('idx_contacts_tenant_created');
                $table->dropIndex('idx_contacts_tenant_owner');
            });
        }

        if (Schema::hasTable('deals')) {
            Schema::table('deals', function (Blueprint $table) {
                $table->dropIndex('idx_deals_tenant_owner');
                $table->dropIndex('idx_deals_tenant_status');
                $table->dropIndex('idx_deals_tenant_pipeline');
                $table->dropIndex('idx_deals_tenant_created');
            });
        }

        if (Schema::hasTable('activities')) {
            Schema::table('activities', function (Blueprint $table) {
                $table->dropIndex('idx_activities_tenant_owner');
                $table->dropIndex('idx_activities_tenant_scheduled');
                $table->dropIndex('idx_activities_tenant_created');
            });
        }

        if (Schema::hasTable('campaigns')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->dropIndex('idx_campaigns_tenant_status');
                $table->dropIndex('idx_campaigns_tenant_created');
            });
        }

        if (Schema::hasTable('campaign_recipients')) {
            Schema::table('campaign_recipients', function (Blueprint $table) {
                $table->dropIndex('idx_campaign_recipients_tenant');
                $table->dropIndex('idx_campaign_recipients_campaign_status');
            });
        }

        if (Schema::hasTable('assignment_rules')) {
            Schema::table('assignment_rules', function (Blueprint $table) {
                $table->dropIndex('idx_assignment_rules_tenant_active');
                $table->dropIndex('idx_assignment_rules_tenant_priority');
            });
        }

        if (Schema::hasTable('tasks')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropIndex('idx_tasks_tenant_owner');
                $table->dropIndex('idx_tasks_tenant_status');
                $table->dropIndex('idx_tasks_tenant_due_date');
            });
        }

        if (Schema::hasTable('companies')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropIndex('idx_companies_tenant_created');
                $table->dropIndex('idx_companies_tenant_industry');
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex('idx_users_tenant');
                $table->dropIndex('idx_users_team');
            });
        }
    }
};
