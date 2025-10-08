<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SequenceCancelledListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(\App\Events\SequenceCancelled $event): void
    {
        $enrollment = $event->enrollment;
        $reason = $event->reason;
        
        // Create activity log for the target
        $description = "Sequence '{$enrollment->sequence->name}' was cancelled";
        if ($reason) {
            $description .= " (Reason: {$reason})";
        }

        \App\Models\Activity::create([
            'type' => 'sequence_cancelled',
            'subject' => 'Sequence cancelled',
            'description' => $description,
            'status' => 'completed',
            'completed_at' => now(),
            'owner_id' => $enrollment->created_by,
            'tenant_id' => $enrollment->tenant_id,
            'related_type' => $enrollment->target_type,
            'related_id' => $enrollment->target_id,
        ]);

        // Log the cancellation
        \Illuminate\Support\Facades\Log::info('Sequence cancelled', [
            'enrollment_id' => $enrollment->id,
            'sequence_id' => $enrollment->sequence_id,
            'target_type' => $enrollment->target_type,
            'target_id' => $enrollment->target_id,
            'reason' => $reason,
            'tenant_id' => $enrollment->tenant_id,
        ]);
    }
}
