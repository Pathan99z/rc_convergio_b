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
        // Fix invalid related_type values
        DB::table('activities')
            ->where('related_type', 'document')
            ->update(['related_type' => 'App\Models\Document']);
            
        DB::table('activities')
            ->where('related_type', 'deal')
            ->update(['related_type' => 'App\Models\Deal']);
            
        DB::table('activities')
            ->where('related_type', 'contact')
            ->update(['related_type' => 'App\Models\Contact']);
            
        DB::table('activities')
            ->where('related_type', 'company')
            ->update(['related_type' => 'App\Models\Company']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse the changes
        DB::table('activities')
            ->where('related_type', 'App\Models\Document')
            ->update(['related_type' => 'document']);
            
        DB::table('activities')
            ->where('related_type', 'App\Models\Deal')
            ->update(['related_type' => 'deal']);
            
        DB::table('activities')
            ->where('related_type', 'App\Models\Contact')
            ->update(['related_type' => 'contact']);
            
        DB::table('activities')
            ->where('related_type', 'App\Models\Company')
            ->update(['related_type' => 'company']);
    }
};
