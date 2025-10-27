<?php

namespace App\Services;

use App\Jobs\ProcessCampaignAutomation;
use App\Models\CampaignAutomation;
use App\Models\Contact;
use App\Models\Campaign;
use App\Models\CampaignTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

            // Find all active automations for this trigger event and tenant
            $automations = CampaignAutomation::where('trigger_event', $triggerEvent)
                ->where('tenant_id', $contact->tenant_id)
                ->where('is_active', true)
                ->get();

            Log::info('Found automations for trigger', [
                'trigger_event' => $triggerEvent,
                'tenant_id' => $contact->tenant_id,
                'automation_count' => $automations->count()
            ]);

            // Process automations immediately (synchronously)
            foreach ($automations as $automation) {
                $this->processAutomation($automation, $contactId, $triggerData);
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
     * Process a single automation immediately.
     */
    private function processAutomation(CampaignAutomation $automation, int $contactId, array $triggerData = []): void
    {
        try {
            Log::info('Processing automation immediately', [
                'automation_id' => $automation->id,
                'campaign_id' => $automation->campaign_id,
                'template_id' => $automation->template_id,
                'content_type' => $automation->content_type,
                'contact_id' => $contactId,
                'trigger_event' => $automation->trigger_event,
                'action' => $automation->action
            ]);

            // Get the contact
            $contact = Contact::find($contactId);
            if (!$contact) {
                Log::error('Contact not found for automation', ['contact_id' => $contactId]);
                return;
            }

            // Handle template-based automations (no campaign required)
            if ($automation->content_type === 'template' && $automation->template_id) {
                $this->processTemplateAutomation($automation, $contact);
            } else {
                // Handle campaign-based automations (backward compatibility)
                $campaign = \App\Models\Campaign::find($automation->campaign_id);
                if (!$campaign) {
                    Log::error('Campaign not found for automation', ['campaign_id' => $automation->campaign_id]);
                    return;
                }
                $this->processAction($automation, $campaign, $contact);
            }

            Log::info('Automation processed successfully', [
                'automation_id' => $automation->id,
                'campaign_id' => $automation->campaign_id,
                'template_id' => $automation->template_id,
                'content_type' => $automation->content_type,
                'contact_id' => $contactId
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process automation', [
                'automation_id' => $automation->id,
                'contact_id' => $contactId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Process template-based automation (no campaign required).
     */
    private function processTemplateAutomation(CampaignAutomation $automation, Contact $contact): void
    {
        switch ($automation->action) {
            case 'send_email':
                $this->sendTemplateEmail($automation, $contact);
                break;
                
            case 'add_to_segment':
                $this->addToSegment($automation, $contact);
                break;
                
            case 'update_contact':
                $this->updateContact($automation, $contact);
                break;
                
            default:
                Log::warning('Unknown automation action for template automation', [
                    'action' => $automation->action,
                    'automation_id' => $automation->id
                ]);
        }
    }

    /**
     * Send email using template (no campaign required).
     */
    private function sendTemplateEmail(CampaignAutomation $automation, Contact $contact): void
    {
        try {
            // Get template content and subject
            $emailContent = $this->getTemplateContent($automation->template_id, $contact);
            $subject = $this->getTemplateSubject($automation->template_id, $contact);
            
            // Send email immediately using Laravel Mail
            Mail::html($emailContent, function ($message) use ($subject, $contact) {
                $message->to($contact->email, $contact->first_name . ' ' . $contact->last_name)
                        ->subject($subject)
                        ->from(config('mail.from.address'), config('mail.from.name'));
            });

            // Log the automation execution
            $this->logAutomationExecution($automation, $contact, 'success');

            Log::info('Template automation email sent successfully', [
                'automation_id' => $automation->id,
                'template_id' => $automation->template_id,
                'content_type' => $automation->content_type,
                'contact_id' => $contact->id,
                'email' => $contact->email
            ]);

        } catch (\Exception $e) {
            // Log failed execution
            $this->logAutomationExecution($automation, $contact, 'failed', $e->getMessage());
            
            Log::error('Failed to send template automation email', [
                'automation_id' => $automation->id,
                'template_id' => $automation->template_id,
                'content_type' => $automation->content_type,
                'contact_id' => $contact->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
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
     * Send email immediately using Laravel Mail.
     */
    private function sendEmail(CampaignAutomation $automation, Campaign $campaign, Contact $contact): void
    {
        try {
            // Determine content source based on automation configuration
            if ($automation->content_type === 'template' && $automation->template_id) {
                // Use template content (new professional approach)
                $emailContent = $this->getTemplateContent($automation->template_id, $contact);
                $subject = $this->getTemplateSubject($automation->template_id, $contact);
            } else {
                // Use campaign content (backward compatibility)
                $emailContent = $this->generateEmailContent($campaign, $contact);
                $subject = $campaign->subject;
            }
            
            // Send email immediately using Laravel Mail
            Mail::html($emailContent, function ($message) use ($subject, $contact) {
                $message->to($contact->email, $contact->first_name . ' ' . $contact->last_name)
                        ->subject($subject)
                        ->from(config('mail.from.address'), config('mail.from.name'));
            });

            // Log the automation execution
            $this->logAutomationExecution($automation, $contact, 'success');

            Log::info('Automation email sent successfully', [
                'automation_id' => $automation->id,
                'campaign_id' => $campaign->id,
                'template_id' => $automation->template_id,
                'content_type' => $automation->content_type,
                'contact_id' => $contact->id,
                'email' => $contact->email
            ]);

        } catch (\Exception $e) {
            // Log failed execution
            $this->logAutomationExecution($automation, $contact, 'failed', $e->getMessage());
            
            Log::error('Failed to send automation email', [
                'automation_id' => $automation->id,
                'campaign_id' => $campaign->id,
                'template_id' => $automation->template_id,
                'content_type' => $automation->content_type,
                'contact_id' => $contact->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Generate email content from campaign.
     */
    private function generateEmailContent(Campaign $campaign, Contact $contact): string
    {
        $content = $campaign->content;
        
        // Replace placeholders with contact data
        $placeholders = [
            '{{first_name}}' => $contact->first_name,
            '{{last_name}}' => $contact->last_name,
            '{{email}}' => $contact->email,
            '{{phone}}' => $contact->phone ?? '',
            '{{company}}' => $contact->company->name ?? '',
        ];
        
        foreach ($placeholders as $placeholder => $value) {
            $content = str_replace($placeholder, $value, $content);
        }
        
        return $content;
    }

    /**
     * Get template content with personalization.
     */
    private function getTemplateContent(int $templateId, Contact $contact): string
    {
        $template = CampaignTemplate::find($templateId);
        if (!$template) {
            throw new \Exception("Template not found: {$templateId}");
        }

        $content = $template->content;
        
        // Replace placeholders with contact data
        $placeholders = [
            '{{first_name}}' => $contact->first_name,
            '{{last_name}}' => $contact->last_name,
            '{{email}}' => $contact->email,
            '{{phone}}' => $contact->phone ?? '',
            '{{company}}' => $contact->company->name ?? '',
        ];
        
        foreach ($placeholders as $placeholder => $value) {
            $content = str_replace($placeholder, $value, $content);
        }
        
        return $content;
    }

    /**
     * Get template subject with personalization.
     */
    private function getTemplateSubject(int $templateId, Contact $contact): string
    {
        $template = CampaignTemplate::find($templateId);
        if (!$template) {
            throw new \Exception("Template not found: {$templateId}");
        }

        $subject = $template->subject;
        
        // Replace placeholders with contact data
        $placeholders = [
            '{{first_name}}' => $contact->first_name,
            '{{last_name}}' => $contact->last_name,
            '{{email}}' => $contact->email,
            '{{phone}}' => $contact->phone ?? '',
            '{{company}}' => $contact->company->name ?? '',
        ];
        
        foreach ($placeholders as $placeholder => $value) {
            $subject = str_replace($placeholder, $value, $subject);
        }
        
        return $subject;
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

    /**
     * Log automation execution.
     */
    private function logAutomationExecution(CampaignAutomation $automation, Contact $contact, string $status, ?string $errorMessage = null): void
    {
        try {
            DB::table('campaign_automation_logs')->insert([
                'automation_id' => $automation->id,
                'contact_id' => $contact->id,
                'executed_at' => now(),
                'status' => $status,
                'error_message' => $errorMessage,
                'tenant_id' => $automation->tenant_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log automation execution', [
                'automation_id' => $automation->id,
                'contact_id' => $contact->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Trigger automations for email opened event.
     */
    public function triggerEmailOpened(int $campaignId, int $contactId): void
    {
        Log::info('Email opened automation triggered', [
            'campaign_id' => $campaignId,
            'contact_id' => $contactId
        ]);

        // Find automations for this specific campaign
        $automations = CampaignAutomation::where('trigger_event', 'email_opened')
            ->where('campaign_id', $campaignId)  // Specific campaign only
            ->where('is_active', true)
            ->get();

        Log::info('Found email opened automations', [
            'campaign_id' => $campaignId,
            'contact_id' => $contactId,
            'automation_count' => $automations->count()
        ]);

        foreach ($automations as $automation) {
            $this->processAutomation($automation, $contactId);
        }
    }

    /**
     * Trigger automations for link clicked event.
     */
    public function triggerLinkClicked(int $campaignId, int $contactId, ?string $linkUrl = null): void
    {
        Log::info('Link clicked automation triggered', [
            'campaign_id' => $campaignId,
            'contact_id' => $contactId,
            'link_url' => $linkUrl
        ]);

        // Find automations for this specific campaign
        $automations = CampaignAutomation::where('trigger_event', 'link_clicked')
            ->where('campaign_id', $campaignId)  // Specific campaign only
            ->where('is_active', true)
            ->get();

        Log::info('Found link clicked automations', [
            'campaign_id' => $campaignId,
            'contact_id' => $contactId,
            'automation_count' => $automations->count()
        ]);

        foreach ($automations as $automation) {
            $this->processAutomation($automation, $contactId);
        }
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
