<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Add tenant_id column if it doesn't exist
        if (!Schema::hasColumn('teams_oauth_tokens', 'tenant_id')) {
            Schema::table('teams_oauth_tokens', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('user_id');
            });
        }

        // Step 2: Populate tenant_id from user_id
        DB::statement('UPDATE teams_oauth_tokens SET tenant_id = user_id WHERE tenant_id IS NULL');

        // Step 3: Make tenant_id NOT NULL
        if (Schema::hasColumn('teams_oauth_tokens', 'tenant_id')) {
            Schema::table('teams_oauth_tokens', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
            });
        }

        // Step 4: Temporarily drop user_id foreign key to allow dropping composite index
        $userFkExists = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'teams_oauth_tokens' 
            AND CONSTRAINT_NAME = 'teams_oauth_tokens_user_id_foreign'
        ");
        
        if (!empty($userFkExists)) {
            DB::statement('ALTER TABLE teams_oauth_tokens DROP FOREIGN KEY teams_oauth_tokens_user_id_foreign');
        }

        // Step 5: Drop old composite index (now safe since FK is dropped)
        $indexExists = DB::select("SHOW INDEX FROM teams_oauth_tokens WHERE Key_name = 'teams_oauth_tokens_user_id_expires_at_index'");
        if (!empty($indexExists)) {
            Schema::table('teams_oauth_tokens', function (Blueprint $table) {
                $table->dropIndex(['user_id', 'expires_at']);
            });
        }

        // Step 6: Recreate user_id foreign key (creates regular index automatically)
        Schema::table('teams_oauth_tokens', function (Blueprint $table) {
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });

        // Step 7: Add tenant_id foreign key if it doesn't exist
        $tenantFkExists = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'teams_oauth_tokens' 
            AND CONSTRAINT_NAME = 'teams_oauth_tokens_tenant_id_foreign'
        ");
        if (empty($tenantFkExists)) {
            Schema::table('teams_oauth_tokens', function (Blueprint $table) {
                $table->foreign('tenant_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('cascade');
            });
        }

        // Step 8: Add new unique constraint (user_id + tenant_id)
        $uniqueExists = DB::select("SHOW INDEX FROM teams_oauth_tokens WHERE Key_name = 'teams_oauth_tokens_user_tenant_unique'");
        if (empty($uniqueExists)) {
            Schema::table('teams_oauth_tokens', function (Blueprint $table) {
                $table->unique(['user_id', 'tenant_id'], 'teams_oauth_tokens_user_tenant_unique');
            });
        }

        // Step 9: Add indexes if they don't exist
        $tenantIndexExists = DB::select("SHOW INDEX FROM teams_oauth_tokens WHERE Key_name = 'teams_oauth_tokens_tenant_id_index'");
        if (empty($tenantIndexExists)) {
            Schema::table('teams_oauth_tokens', function (Blueprint $table) {
                $table->index('tenant_id', 'teams_oauth_tokens_tenant_id_index');
            });
        }

        $expiresIndexExists = DB::select("SHOW INDEX FROM teams_oauth_tokens WHERE Key_name = 'teams_oauth_tokens_expires_at_index'");
        if (empty($expiresIndexExists)) {
            Schema::table('teams_oauth_tokens', function (Blueprint $table) {
                $table->index('expires_at', 'teams_oauth_tokens_expires_at_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams_oauth_tokens', function (Blueprint $table) {
            // Drop indexes and constraints
            $table->dropForeign(['tenant_id']);
            $table->dropUnique('teams_oauth_tokens_user_tenant_unique');
            $table->dropIndex('teams_oauth_tokens_tenant_id_index');
            $table->dropIndex('teams_oauth_tokens_expires_at_index');
            
            // Drop tenant_id column
            $table->dropColumn('tenant_id');
            
            // Restore old index
            $table->index(['user_id', 'expires_at']);
        });
    }
};
