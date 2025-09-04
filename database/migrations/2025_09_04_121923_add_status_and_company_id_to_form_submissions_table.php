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
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('contact_id');
            $table->unsignedBigInteger('company_id')->nullable()->after('status');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('set null');
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropIndex(['status', 'created_at']);
            $table->dropColumn(['status', 'company_id']);
        });
    }
};
