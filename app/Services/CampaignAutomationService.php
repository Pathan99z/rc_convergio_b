<?php

namespace App\Services;

use App\Jobs\ProcessCampaignAutomation;
use App\Models\CampaignAutomation;
use App\Models\Contact;
use Illuminate\Support\Facades\Log;

class CampaignAutomationService
{
    /**
     * Trigger automations for a specific event.
     */
    public function triggerAutomations(string $triggerEvent, int $contactId, array $triggerData = []): void
    {
        try {
            Log::info('Triggering campaign automations', [
                'trigger_event' => $triggerEvent,
                'contact_id' => $contactId,
                'trigger_data' => $triggerData
            ]);

            // Get the contact to determine tenant
            $contact = Contact::find($contactId);
            if (!$contact) {
                Log::error('Contact not found for automation trigger', ['contact_id' => $contactId]);
                return;
            }

            // Find all automations for this trigger event and tenant
            $automations = CampaignAutomation::where('trigger_event', $triggerEvent)
                ->where('tenant_id', $contact->tenant_id)
                ->get();

            Log::info('Found automations for trigger', [
                'trigger_event' => $triggerEvent,
                'tenant_id' => $contact->tenant_id,
                'automation_count' => $automations->count()
            ]);

            // Dispatch automation jobs
            foreach ($automations as $automation) {
                $this->dispatchAutomation($automation, $contactId, $triggerData);
            }

        } catch (\Exception $e) {
            Log::error('Failed to trigger campaign automations', [
                'trigger_event' => $triggerEvent,
                'contact_id' => $contactId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Dispatch a single automation job.
     */
    private function dispatchAutomation(CampaignAutomation $automation, int $contactId, array $triggerData = []): void
    {
        try {
            // Dispatch the automation job with delay
            $job = new ProcessCampaignAutomation($automation->id, $contactId, $triggerData);
            dispatch($job)->delay(now()->addMinutes($automation->delay_minutes));

            Log::info('Automation job dispatched', [
                'automation_id' => $automation->id,
                'campaign_id' => $automation->campaign_id,
                'contact_id' => $contactId,
                'delay_minutes' => $automation->delay_minutes,
                'trigger_event' => $automation->trigger_event
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to dispatch automation job', [
                'automation_id' => $automation->id,
                'contact_id' => $contactId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Trigger form submission automation.
     */
    public function triggerFormSubmission(int $contactId, int $formId, array $formData = []): void
    {
        $this->triggerAutomations('form_submitted', $contactId, [
            'form_id' => $formId,
            'form_data' => $formData,
            'triggered_at' => now()->toISOString()
        ]);
    }

    /**
     * Trigger segment join automation.
     */
    public function triggerSegmentJoin(int $contactId, int $segmentId, array $segmentData = []): void
    {
        $this->triggerAutomations('segment_joined', $contactId, [
            'segment_id' => $segmentId,
            'segment_data' => $segmentData,
            'triggered_at' => now()->toISOString()
        ]);
    }

    /**
     * Trigger contact creation automation.
     */
    public function triggerContactCreated(int $contactId, array $contactData = []): void
    {
        $this->triggerAutomations('contact_created', $contactId, [
            'contact_data' => $contactData,
            'triggered_at' => now()->toISOString()
        ]);
    }

    /**
     * Trigger deal creation automation.
     */
    public function triggerDealCreated(int $contactId, int $dealId, array $dealData = []): void
    {
        $this->triggerAutomations('deal_created', $contactId, [
            'deal_id' => $dealId,
            'deal_data' => $dealData,
            'triggered_at' => now()->toISOString()
        ]);
    }

    /**
     * Trigger deal update automation.
     */
    public function triggerDealUpdated(int $contactId, int $dealId, array $dealData = [], array $changes = []): void
    {
        $this->triggerAutomations('deal_updated', $contactId, [
            'deal_id' => $dealId,
            'deal_data' => $dealData,
            'changes' => $changes,
            'triggered_at' => now()->toISOString()
        ]);
    }

    /**
     * Get automation statistics for a tenant.
     */
    public function getAutomationStats(int $tenantId): array
    {
        $automations = CampaignAutomation::where('tenant_id', $tenantId)->get();

        return [
            'total_automations' => $automations->count(),
            'by_trigger_event' => $automations->groupBy('trigger_event')->map->count(),
            'by_action' => $automations->groupBy('action')->map->count(),
            'by_campaign' => $automations->groupBy('campaign_id')->map->count(),
        ];
    }
}
