<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SequenceStepExecutedListener
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
    public function handle(\App\Events\SequenceStepExecuted $event): void
    {
        $enrollment = $event->enrollment;
        $step = $event->step;
        $status = $event->status;
        $notes = $event->notes;
        
        // Create activity log for the target
        $activityType = match($step->action_type) {
            'email' => 'sequence_email_sent',
            'task' => 'sequence_task_created',
            'wait' => 'sequence_wait_completed',
            default => 'sequence_step_executed',
        };

        $subject = match($step->action_type) {
            'email' => 'Sequence email sent',
            'task' => 'Sequence task created',
            'wait' => 'Sequence wait completed',
            default => 'Sequence step executed',
        };

        $description = match($step->action_type) {
            'email' => "Email sent from sequence: {$enrollment->sequence->name}",
            'task' => "Task created from sequence: {$enrollment->sequence->name} - {$step->task_title}",
            'wait' => "Wait step completed in sequence: {$enrollment->sequence->name}",
            default => "Step executed in sequence: {$enrollment->sequence->name}",
        };

        if ($status === 'failed' && $notes) {
            $description .= " (Failed: {$notes})";
        }

        \App\Models\Activity::create([
            'type' => $activityType,
            'subject' => $subject,
            'description' => $description,
            'status' => $status === 'success' ? 'completed' : 'failed',
            'completed_at' => $status === 'success' ? now() : null,
            'owner_id' => $enrollment->created_by,
            'tenant_id' => $enrollment->tenant_id,
            'related_type' => $enrollment->target_type,
            'related_id' => $enrollment->target_id,
        ]);

        // Log the step execution
        \Illuminate\Support\Facades\Log::info('Sequence step executed', [
            'enrollment_id' => $enrollment->id,
            'step_id' => $step->id,
            'action_type' => $step->action_type,
            'status' => $status,
            'notes' => $notes,
            'tenant_id' => $enrollment->tenant_id,
        ]);
    }
}
