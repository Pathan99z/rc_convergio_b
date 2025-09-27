<?php

namespace App\Console\Commands;

use App\Models\IntentActionScore;
use App\Models\User;
use Illuminate\Console\Command;

class InitializeScoringConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scoring:initialize {--tenant= : Initialize for specific tenant ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize scoring configuration for tenants';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenantId = $this->option('tenant');
        
        if ($tenantId) {
            $this->initializeForTenant($tenantId);
        } else {
            $this->initializeForAllTenants();
        }
    }

    /**
     * Initialize scoring configuration for a specific tenant.
     */
    private function initializeForTenant(int $tenantId): void
    {
        $this->info("Initializing scoring configuration for tenant {$tenantId}...");
        
        try {
            IntentActionScore::initializeForTenant($tenantId);
            $this->info("✅ Scoring configuration initialized for tenant {$tenantId}");
        } catch (\Exception $e) {
            $this->error("❌ Failed to initialize scoring configuration for tenant {$tenantId}: " . $e->getMessage());
        }
    }

    /**
     * Initialize scoring configuration for all tenants.
     */
    private function initializeForAllTenants(): void
    {
        $this->info("Initializing scoring configuration for all tenants...");
        
        // Get all users and extract unique tenant_ids
        $users = User::all();
        $tenants = $users->pluck('tenant_id')->unique()->filter()->values();
        
        if ($tenants->isEmpty()) {
            $this->warn("No tenants found to initialize scoring configuration for.");
            return;
        }
        
        $bar = $this->output->createProgressBar($tenants->count());
        $bar->start();
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($tenants as $tenantId) {
            try {
                IntentActionScore::initializeForTenant($tenantId);
                $successCount++;
            } catch (\Exception $e) {
                $errorCount++;
                $this->newLine();
                $this->error("Failed to initialize for tenant {$tenantId}: " . $e->getMessage());
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        
        $this->info("✅ Successfully initialized scoring configuration for {$successCount} tenants");
        if ($errorCount > 0) {
            $this->error("❌ Failed to initialize scoring configuration for {$errorCount} tenants");
        }
    }
}