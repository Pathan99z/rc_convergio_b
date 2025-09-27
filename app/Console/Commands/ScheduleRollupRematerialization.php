<?php

namespace App\Console\Commands;

use App\Jobs\RematerializeAnalyticsRollups;
use Illuminate\Console\Command;

class ScheduleRollupRematerialization extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rollups:rematerialize {--tenant= : Rematerialize for specific tenant ID} {--days=7 : Number of days to rematerialize}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rematerialize analytics rollups for the last N days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenantId = $this->option('tenant');
        $days = (int) $this->option('days');

        $this->info("Scheduling rollup rematerialization...");
        $this->info("Tenant: " . ($tenantId ?: 'All'));
        $this->info("Days: {$days}");

        try {
            RematerializeAnalyticsRollups::dispatch($tenantId, $days);
            
            $this->info("✅ Rollup rematerialization job dispatched successfully");
            
        } catch (\Exception $e) {
            $this->error("❌ Failed to dispatch rollup rematerialization job: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}