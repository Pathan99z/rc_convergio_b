<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\AnalyticsRollupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RematerializeAnalyticsRollups implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tenantId;
    protected $days;

    /**
     * Create a new job instance.
     */
    public function __construct(?int $tenantId = null, int $days = 7)
    {
        $this->tenantId = $tenantId;
        $this->days = $days;
    }

    /**
     * Execute the job.
     */
    public function handle(AnalyticsRollupService $rollupService): void
    {
        try {
            $endDate = now()->format('Y-m-d');
            $startDate = now()->subDays($this->days)->format('Y-m-d');

            if ($this->tenantId) {
                // Rematerialize for specific tenant
                $this->rematerializeForTenant($rollupService, $this->tenantId, $startDate, $endDate);
            } else {
                // Rematerialize for all tenants
                $this->rematerializeForAllTenants($rollupService, $startDate, $endDate);
            }

        } catch (\Exception $e) {
            Log::error('Failed to rematerialize analytics rollups', [
                'tenant_id' => $this->tenantId,
                'days' => $this->days,
                'error' => $e->getMessage()
            ]);

            // Re-throw to trigger job retry
            throw $e;
        }
    }

    /**
     * Rematerialize rollups for a specific tenant.
     */
    private function rematerializeForTenant(AnalyticsRollupService $rollupService, int $tenantId, string $startDate, string $endDate): void
    {
        Log::info('Starting rollup rematerialization for tenant', [
            'tenant_id' => $tenantId,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        $rollupService->rematerializeRollups($tenantId, $startDate, $endDate);

        Log::info('Completed rollup rematerialization for tenant', [
            'tenant_id' => $tenantId
        ]);
    }

    /**
     * Rematerialize rollups for all tenants.
     */
    private function rematerializeForAllTenants(AnalyticsRollupService $rollupService, string $startDate, string $endDate): void
    {
        Log::info('Starting rollup rematerialization for all tenants', [
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        // Get all unique tenant IDs
        $users = User::all();
        $tenants = $users->pluck('tenant_id')->unique()->filter()->values();

        $successCount = 0;
        $errorCount = 0;

        foreach ($tenants as $tenantId) {
            try {
                $rollupService->rematerializeRollups($tenantId, $startDate, $endDate);
                $successCount++;
            } catch (\Exception $e) {
                $errorCount++;
                Log::error('Failed to rematerialize rollups for tenant', [
                    'tenant_id' => $tenantId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Completed rollup rematerialization for all tenants', [
            'total_tenants' => $tenants->count(),
            'success_count' => $successCount,
            'error_count' => $errorCount
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Analytics rollup rematerialization job failed permanently', [
            'tenant_id' => $this->tenantId,
            'days' => $this->days,
            'error' => $exception->getMessage()
        ]);
    }
}