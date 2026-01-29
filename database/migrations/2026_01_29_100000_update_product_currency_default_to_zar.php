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
        // Update the default value for currency column to ZAR
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'currency')) {
            // Change the default value for new records
            DB::statement("ALTER TABLE `products` MODIFY COLUMN `currency` VARCHAR(3) DEFAULT 'ZAR'");
            
            // Update existing products that have USD to ZAR (optional - only if you want to change existing data)
            // Uncomment the line below if you want to update existing USD products to ZAR
            // DB::table('products')->where('currency', 'USD')->update(['currency' => 'ZAR']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'currency')) {
            // Revert default back to USD
            DB::statement("ALTER TABLE `products` MODIFY COLUMN `currency` VARCHAR(3) DEFAULT 'USD'");
        }
    }
};

