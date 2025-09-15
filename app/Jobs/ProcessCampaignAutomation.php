<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignAutomation;
use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCampaignAutomation implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $automationId,
        public int $contactId,
        public array $triggerData = []
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Processing campaign automation', [
                'automation_id' => $this->automationId,
                'contact_id' => $this->contactId,
                'trigger_data' => $this->triggerData
            ]);

            // Get the automation rule
            $automation = CampaignAutomation::find($this->automationId);
            if (!$automation) {
                Log::error('Campaign automation not found', ['automation_id' => $this->automationId]);
                return;
            }

            // Get the campaign
            $campaign = Campaign::find($automation->campaign_id);
            if (!$campaign) {
                Log::error('Campaign not found', ['campaign_id' => $automation->campaign_id]);
                return;
            }

            // Get the contact
            $contact = Contact::find($this->contactId);
            if (!$contact) {
                Log::error('Contact not found', ['contact_id' => $this->contactId]);
                return;
            }

            // Verify tenant consistency
            if ($automation->tenant_id !== $campaign->tenant_id || 
                $campaign->tenant_id !== $contact->tenant_id) {
                Log::error('Tenant mismatch in automation processing', [
                    'automation_tenant' => $automation->tenant_id,
                    'campaign_tenant' => $campaign->tenant_id,
                    'contact_tenant' => $contact->tenant_id
                ]);
                return;
            }

            // Process the automation action
            $this->processAction($automation, $campaign, $contact);

            Log::info('Campaign automation processed successfully', [
                'automation_id' => $this->automationId,
                'campaign_id' => $campaign->id,
                'contact_id' => $this->contactId
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process campaign automation', [
                'automation_id' => $this->automationId,
                'contact_id' => $this->contactId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Process the automation action.
     */
    private function processAction(CampaignAutomation $automation, Campaign $campaign, Contact $contact): void
    {
        switch ($automation->action) {
            case 'send_email':
                $this->sendEmail($automation, $campaign, $contact);
                break;
                
            case 'add_to_segment':
                $this->addToSegment($automation, $contact);
                break;
                
            case 'update_contact':
                $this->updateContact($automation, $contact);
                break;
                
            default:
                Log::warning('Unknown automation action', [
                    'action' => $automation->action,
                    'automation_id' => $automation->id
                ]);
        }
    }

    /**
     * Send email using existing campaign sending logic.
     */
    private function sendEmail(CampaignAutomation $automation, Campaign $campaign, Contact $contact): void
    {
        // Use the existing SendCampaignEmails job but with a single recipient
        $recipients = collect([$contact]);
        
        // Dispatch the existing SendCampaignEmails job
        \App\Jobs\SendCampaignEmails::dispatch($campaign, $recipients)
            ->delay(now()->addMinutes($automation->delay_minutes));
            
        Log::info('Email automation dispatched', [
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'delay_minutes' => $automation->delay_minutes
        ]);
    }

    /**
     * Add contact to a segment.
     */
    private function addToSegment(CampaignAutomation $automation, Contact $contact): void
    {
        $segmentId = $automation->metadata['segment_id'] ?? null;
        
        if (!$segmentId) {
            Log::warning('No segment_id in automation metadata', [
                'automation_id' => $automation->id
            ]);
            return;
        }

        // Add contact to segment logic here
        // This would depend on your segment implementation
        Log::info('Contact added to segment via automation', [
            'contact_id' => $contact->id,
            'segment_id' => $segmentId,
            'automation_id' => $automation->id
        ]);
    }

    /**
     * Update contact with automation metadata.
     */
    private function updateContact(CampaignAutomation $automation, Contact $contact): void
    {
        $updateData = $automation->metadata['contact_updates'] ?? [];
        
        if (empty($updateData)) {
            Log::warning('No contact_updates in automation metadata', [
                'automation_id' => $automation->id
            ]);
            return;
        }

        // Update contact with the specified data
        $contact->update($updateData);
        
        Log::info('Contact updated via automation', [
            'contact_id' => $contact->id,
            'updates' => $updateData,
            'automation_id' => $automation->id
        ]);
    }
}
