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
        Schema::table('quotes', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->after('deal_id')->constrained('contacts')->onDelete('set null');
        });

        // Update existing quotes to set contact_id from deal (database agnostic)
        $quotes = \DB::table('quotes')
            ->join('deals', 'quotes.deal_id', '=', 'deals.id')
            ->whereNull('quotes.contact_id')
            ->whereNotNull('deals.contact_id')
            ->select('quotes.id', 'deals.contact_id')
            ->get();

        foreach ($quotes as $quote) {
            \DB::table('quotes')
                ->where('id', $quote->id)
                ->update(['contact_id' => $quote->contact_id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
            $table->dropColumn('contact_id');
        });
    }
};
