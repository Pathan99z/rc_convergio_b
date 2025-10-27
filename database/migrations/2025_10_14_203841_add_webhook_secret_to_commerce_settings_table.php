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
        Schema::table('commerce_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('commerce_settings', 'webhook_secret')) {
                $table->text('webhook_secret')->nullable();
            }
            if (!Schema::hasColumn('commerce_settings', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commerce_settings', function (Blueprint $table) {
            if (Schema::hasColumn('commerce_settings', 'webhook_secret')) {
                $table->dropColumn('webhook_secret');
            }
            if (Schema::hasColumn('commerce_settings', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }
        });
    }
};
