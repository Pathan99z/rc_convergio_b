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
        Schema::table('user_seo_sites', function (Blueprint $table) {
            $table->text('google_access_token')->nullable()->after('gsc_property');
            $table->text('google_refresh_token')->nullable()->after('google_access_token');
            $table->timestamp('google_token_expires_at')->nullable()->after('google_refresh_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_seo_sites', function (Blueprint $table) {
            $table->dropColumn(['google_access_token', 'google_refresh_token', 'google_token_expires_at']);
        });
    }
};
