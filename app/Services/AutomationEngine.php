<?php

namespace App\Services;

use App\Models\Automation;
use App\Models\AutomationLog;
use App\Models\Contact;
use App\Models\CampaignRecipient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class AutomationEngine
{
    /**
     * Process a trigger event and execute matching automations.
     */
    public function processTrigger(string $triggerEvent, int $contactId, array $metadata = []): void
    {
        try {
            // Get all active automations for this trigger event
            $automations = Automation::active()
                ->forTrigger($triggerEvent)
                ->get();

            foreach ($automations as $automation) {
                $this->scheduleAutomation($automation, $contactId, $metadata);
            }

        } catch (\Exception $e) {
            Log::error('Automation engine trigger processing failed', [
                'trigger_event' => $triggerEvent,
                'contact_id' => $contactId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Schedule an automation for execution.
     */
    protected function scheduleAutomation(Automation $automation, int $contactId, array $metadata = []): void
    {
        try {
            DB::beginTransaction();

            // Create automation log entry
            $log = AutomationLog::create([
                'automation_id' => $automation->id,
                'contact_id' => $contactId,
                'executed_at' => $automation->delay_minutes > 0 
                    ? Carbon::now()->addMinutes($automation->delay_minutes)
                    : Carbon::now(),
                'status' => 'pending',
                'metadata' => $metadata,
                'tenant_id' => $automation->tenant_id,
            ]);

            DB::commit();

            // If no delay, execute immediately
            if ($automation->delay_minutes === 0) {
                $this->executeAutomation($log);
            } else {
                // Schedule for later execution (you might want to use Laravel's job queue here)
                Log::info('Automation scheduled for later execution', [
                    'automation_id' => $automation->id,
                    'contact_id' => $contactId,
                    'executed_at' => $log->executed_at
                ]);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to schedule automation', [
                'automation_id' => $automation->id,
                'contact_id' => $contactId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Execute an automation.
     */
    public function executeAutomation(AutomationLog $log): void
    {
        try {
            $automation = $log->automation;
            $contact = $log->contact;

            if (!$automation->canExecute()) {
                $log->markAsFailed('Automation is not active or has been deleted');
                return;
            }

            // Execute the action based on automation type
            switch ($automation->action) {
                case 'send_email':
                    $this->executeSendEmail($automation, $contact, $log);
                    break;
                case 'add_tag':
                    $this->executeAddTag($automation, $contact, $log);
                    break;
                case 'update_field':
                    $this->executeUpdateField($automation, $contact, $log);
                    break;
                case 'create_task':
                    $this->executeCreateTask($automation, $contact, $log);
                    break;
                default:
                    $log->markAsFailed('Unknown action: ' . $automation->action);
            }

        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            Log::error('Automation execution failed', [
                'automation_id' => $log->automation_id,
                'contact_id' => $log->contact_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Execute send email action.
     */
    protected function executeSendEmail(Automation $automation, Contact $contact, AutomationLog $log): void
    {
        $metadata = $automation->metadata ?? [];
        $emailTemplateId = $metadata['email_template_id'] ?? null;
        $subject = $metadata['subject'] ?? 'Automated Email';
        $fromName = $metadata['from_name'] ?? config('mail.from.name');
        $fromEmail = $metadata['from_email'] ?? config('mail.from.address');

        if (!$emailTemplateId) {
            $log->markAsFailed('Email template ID not specified in metadata');
            return;
        }

        // Here you would implement email sending logic
        // For now, we'll just log the action
        Log::info('Automation: Send email action executed', [
            'automation_id' => $automation->id,
            'contact_id' => $contact->id,
            'email' => $contact->email,
            'template_id' => $emailTemplateId,
            'subject' => $subject
        ]);

        $log->markAsExecuted([
            'action_executed' => 'send_email',
            'template_id' => $emailTemplateId,
            'subject' => $subject,
            'sent_at' => now()
        ]);
    }

    /**
     * Execute add tag action.
     */
    protected function executeAddTag(Automation $automation, Contact $contact, AutomationLog $log): void
    {
        $metadata = $automation->metadata ?? [];
        $tagName = $metadata['tag_name'] ?? null;

        if (!$tagName) {
            $log->markAsFailed('Tag name not specified in metadata');
            return;
        }

        // Here you would implement tag addition logic
        // For now, we'll just log the action
        Log::info('Automation: Add tag action executed', [
            'automation_id' => $automation->id,
            'contact_id' => $contact->id,
            'tag_name' => $tagName
        ]);

        $log->markAsExecuted([
            'action_executed' => 'add_tag',
            'tag_name' => $tagName,
            'executed_at' => now()
        ]);
    }

    /**
     * Execute update field action.
     */
    protected function executeUpdateField(Automation $automation, Contact $contact, AutomationLog $log): void
    {
        $metadata = $automation->metadata ?? [];
        $fieldName = $metadata['field_name'] ?? null;
        $fieldValue = $metadata['field_value'] ?? null;

        if (!$fieldName || $fieldValue === null) {
            $log->markAsFailed('Field name or value not specified in metadata');
            return;
        }

        // Update the contact field
        $contact->update([$fieldName => $fieldValue]);

        Log::info('Automation: Update field action executed', [
            'automation_id' => $automation->id,
            'contact_id' => $contact->id,
            'field_name' => $fieldName,
            'field_value' => $fieldValue
        ]);

        $log->markAsExecuted([
            'action_executed' => 'update_field',
            'field_name' => $fieldName,
            'field_value' => $fieldValue,
            'executed_at' => now()
        ]);
    }

    /**
     * Execute create task action.
     */
    protected function executeCreateTask(Automation $automation, Contact $contact, AutomationLog $log): void
    {
        $metadata = $automation->metadata ?? [];
        $taskTitle = $metadata['task_title'] ?? 'Automated Task';
        $taskDescription = $metadata['task_description'] ?? '';
        $taskDueDate = $metadata['task_due_date'] ?? null;

        // Here you would implement task creation logic
        // For now, we'll just log the action
        Log::info('Automation: Create task action executed', [
            'automation_id' => $automation->id,
            'contact_id' => $contact->id,
            'task_title' => $taskTitle,
            'task_description' => $taskDescription
        ]);

        $log->markAsExecuted([
            'action_executed' => 'create_task',
            'task_title' => $taskTitle,
            'task_description' => $taskDescription,
            'executed_at' => now()
        ]);
    }

    /**
     * Process email tracking events for automations.
     */
    public function processEmailEvent(string $eventType, int $recipientId): void
    {
        try {
            $recipient = CampaignRecipient::find($recipientId);
            if (!$recipient) {
                return;
            }

            $contact = Contact::where('email', $recipient->email)
                ->where('tenant_id', $recipient->tenant_id)
                ->first();

            if (!$contact) {
                return;
            }

            // Map email events to automation triggers
            $triggerMap = [
                'opened' => 'email_opened',
                'clicked' => 'link_clicked',
            ];

            $triggerEvent = $triggerMap[$eventType] ?? null;
            if ($triggerEvent) {
                $this->processTrigger($triggerEvent, $contact->id, [
                    'campaign_id' => $recipient->campaign_id,
                    'recipient_id' => $recipientId,
                    'event_type' => $eventType
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Email event processing for automation failed', [
                'event_type' => $eventType,
                'recipient_id' => $recipientId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
