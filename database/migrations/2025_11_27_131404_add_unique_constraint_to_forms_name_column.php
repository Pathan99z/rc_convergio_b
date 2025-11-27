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
        // First, handle existing duplicate form names by appending a counter
        $duplicates = DB::table('forms')
            ->select('name', 'tenant_id', DB::raw('COUNT(*) as count'))
            ->groupBy('name', 'tenant_id')
            ->having('count', '>', 1)
            ->get();

        foreach ($duplicates as $duplicate) {
            $forms = DB::table('forms')
                ->where('name', $duplicate->name)
                ->where('tenant_id', $duplicate->tenant_id)
                ->orderBy('id')
                ->get();

            // Skip the first one, rename the rest
            $counter = 1;
            foreach ($forms as $index => $form) {
                if ($index > 0) {
                    DB::table('forms')
                        ->where('id', $form->id)
                        ->update([
                            'name' => $duplicate->name . ' (' . $counter . ')',
                            'updated_at' => now()
                        ]);
                    $counter++;
                }
            }
        }

        // Now add the unique constraint
        Schema::table('forms', function (Blueprint $table) {
            // Add unique constraint on name column scoped by tenant_id
            // This ensures form names are unique within each tenant
            $table->unique(['name', 'tenant_id'], 'forms_name_tenant_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            // Drop the unique constraint
            $table->dropUnique('forms_name_tenant_unique');
        });
    }
};
