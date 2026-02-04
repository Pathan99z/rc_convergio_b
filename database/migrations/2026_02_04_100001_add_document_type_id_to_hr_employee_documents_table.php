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
        if (Schema::hasTable('hr_employee_documents')) {
            Schema::table('hr_employee_documents', function (Blueprint $table) {
                // Add document_type_id column (nullable for backward compatibility)
                if (!Schema::hasColumn('hr_employee_documents', 'document_type_id')) {
                    $table->unsignedBigInteger('document_type_id')->nullable()->after('category');
                    
                    // Foreign key constraint
                    $table->foreign('document_type_id')
                        ->references('id')
                        ->on('hr_document_types')
                        ->onDelete('set null'); // Set to null if document type is deleted
                    
                    // Index for performance
                    $table->index('document_type_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('hr_employee_documents')) {
            Schema::table('hr_employee_documents', function (Blueprint $table) {
                if (Schema::hasColumn('hr_employee_documents', 'document_type_id')) {
                    $table->dropForeign(['document_type_id']);
                    $table->dropIndex(['document_type_id']);
                    $table->dropColumn('document_type_id');
                }
            });
        }
    }
};

