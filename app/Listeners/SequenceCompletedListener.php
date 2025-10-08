<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SequenceCompletedListener
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
    public function handle(\App\Events\SequenceCompleted $event): void
    {
        $enrollment = $event->enrollment;
        
        // Create activity log for the target
        \App\Models\Activity::create([
            'type' => 'sequence_completed',
            'subject' => 'Sequence completed',
            'description' => "Sequence '{$enrollment->sequence->name}' has been completed",
            'status' => 'completed',
            'completed_at' => now(),
            'owner_id' => $enrollment->created_by,
            'tenant_id' => $enrollment->tenant_id,
            'related_type' => $enrollment->target_type,
            'related_id' => $enrollment->target_id,
        ]);

        // Log the completion
        \Illuminate\Support\Facades\Log::info('Sequence completed', [
            'enrollment_id' => $enrollment->id,
            'sequence_id' => $enrollment->sequence_id,
            'target_type' => $enrollment->target_type,
            'target_id' => $enrollment->target_id,
            'tenant_id' => $enrollment->tenant_id,
        ]);
    }
}
