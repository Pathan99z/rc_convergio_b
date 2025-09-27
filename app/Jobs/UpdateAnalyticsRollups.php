<?php

namespace App\Jobs;

use App\Models\IntentEvent;
use App\Services\AnalyticsRollupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateAnalyticsRollups implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $eventId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $eventId)
    {
        $this->eventId = $eventId;
    }

    /**
     * Execute the job.
     */
    public function handle(AnalyticsRollupService $rollupService): void
    {
        try {
            $event = IntentEvent::find($this->eventId);
            
            if (!$event) {
                Log::warning('Event not found for rollup update', ['event_id' => $this->eventId]);
                return;
            }

            $rollupService->updateRollupsForEvent($event);

            Log::debug('Analytics rollups updated for event', [
                'event_id' => $this->eventId,
                'tenant_id' => $event->tenant_id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update analytics rollups', [
                'event_id' => $this->eventId,
                'error' => $e->getMessage()
            ]);

            // Re-throw to trigger job retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Analytics rollup job failed permanently', [
            'event_id' => $this->eventId,
            'error' => $exception->getMessage()
        ]);
    }
}