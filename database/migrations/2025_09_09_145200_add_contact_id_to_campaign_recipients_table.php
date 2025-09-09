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
        Schema::table('campaign_recipients', function (Blueprint $table) {
            if (!Schema::hasColumn('campaign_recipients', 'contact_id')) {
                $table->unsignedBigInteger('contact_id')->nullable()->after('campaign_id');
                $table->index('contact_id');
                $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_recipients', function (Blueprint $table) {
            if (Schema::hasColumn('campaign_recipients', 'contact_id')) {
                // Drop FK and index if they exist, then drop the column
                try { $table->dropForeign(['contact_id']); } catch (\Throwable $e) {}
                try { $table->dropIndex(['contact_id']); } catch (\Throwable $e) {}
                $table->dropColumn('contact_id');
            }
        });
    }
};
