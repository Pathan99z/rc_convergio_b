<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\LeadScoringRule;
use Illuminate\Support\Facades\Log;

class LeadScoringEventDetector
{
    protected LeadScoringService $leadScoringService;

    public function __construct(LeadScoringService $leadScoringService)
    {
        $this->leadScoringService = $leadScoringService;
    }

    /**
     * Detect and process email events
     */
    public function detectEmailEvent(string $eventType, int $contactId, array $emailData = []): void
    {
        try {
            $contact = Contact::find($contactId);
            if (!$contact) {
                Log::warning('Contact not found for email event', ['contact_id' => $contactId]);
                return;
            }

            $eventData = [
                'event' => $eventType,
                'contact_id' => $contactId,
                'tenant_id' => $contact->tenant_id,
                'email_id' => $emailData['email_id'] ?? null,
                'campaign_id' => $emailData['campaign_id'] ?? null,
                'subject' => $emailData['subject'] ?? null,
                'timestamp' => now()->toISOString()
            ];

            $this->leadScoringService->processEvent($eventData, $contact->tenant_id);

            Log::info('Email event processed for lead scoring', [
                'event' => $eventType,
                'contact_id' => $contactId,
                'tenant_id' => $contact->tenant_id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process email event for lead scoring', [
                'event' => $eventType,
                'contact_id' => $contactId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Detect website behavior events
     */
    public function detectWebsiteEvent(string $eventType, int $contactId, array $websiteData = []): void
    {
        try {
            $contact = Contact::find($contactId);
            if (!$contact) {
                Log::warning('Contact not found for website event', ['contact_id' => $contactId]);
                return;
            }

            $eventData = [
                'event' => $eventType,
                'contact_id' => $contactId,
                'tenant_id' => $contact->tenant_id,
                'page' => $websiteData['page'] ?? null,
                'url' => $websiteData['url'] ?? null,
                'referrer' => $websiteData['referrer'] ?? null,
                'visit_count' => $websiteData['visit_count'] ?? 1,
                'session_duration' => $websiteData['session_duration'] ?? null,
                'timestamp' => now()->toISOString()
            ];

            $this->leadScoringService->processEvent($eventData, $contact->tenant_id);

            Log::info('Website event processed for lead scoring', [
                'event' => $eventType,
                'contact_id' => $contactId,
                'tenant_id' => $contact->tenant_id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process website event for lead scoring', [
                'event' => $eventType,
                'contact_id' => $contactId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Detect form submission events
     */
    public function detectFormEvent(int $contactId, array $formData = []): void
    {
        try {
            $contact = Contact::find($contactId);
            if (!$contact) {
                Log::warning('Contact not found for form event', ['contact_id' => $contactId]);
                return;
            }

            $eventData = [
                'event' => 'form_submission',
                'contact_id' => $contactId,
                'tenant_id' => $contact->tenant_id,
                'form_id' => $formData['form_id'] ?? null,
                'form_name' => $formData['form_name'] ?? null,
                'fields' => $formData['fields'] ?? [],
                'timestamp' => now()->toISOString()
            ];

            $this->leadScoringService->processEvent($eventData, $contact->tenant_id);

            Log::info('Form event processed for lead scoring', [
                'contact_id' => $contactId,
                'tenant_id' => $contact->tenant_id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process form event for lead scoring', [
                'contact_id' => $contactId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Detect deal events
     */
    public function detectDealEvent(string $eventType, int $contactId, array $dealData = []): void
    {
        try {
            $contact = Contact::find($contactId);
            if (!$contact) {
                Log::warning('Contact not found for deal event', ['contact_id' => $contactId]);
                return;
            }

            $eventData = [
                'event' => $eventType,
                'contact_id' => $contactId,
                'tenant_id' => $contact->tenant_id,
                'deal_id' => $dealData['deal_id'] ?? null,
                'deal_value' => $dealData['deal_value'] ?? null,
                'deal_stage' => $dealData['deal_stage'] ?? null,
                'timestamp' => now()->toISOString()
            ];

            $this->leadScoringService->processEvent($eventData, $contact->tenant_id);

            Log::info('Deal event processed for lead scoring', [
                'event' => $eventType,
                'contact_id' => $contactId,
                'tenant_id' => $contact->tenant_id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process deal event for lead scoring', [
                'event' => $eventType,
                'contact_id' => $contactId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Detect meeting events
     */
    public function detectMeetingEvent(string $eventType, int $contactId, array $meetingData = []): void
    {
        try {
            $contact = Contact::find($contactId);
            if (!$contact) {
                Log::warning('Contact not found for meeting event', ['contact_id' => $contactId]);
                return;
            }

            $eventData = [
                'event' => $eventType,
                'contact_id' => $contactId,
                'tenant_id' => $contact->tenant_id,
                'meeting_id' => $meetingData['meeting_id'] ?? null,
                'meeting_type' => $meetingData['meeting_type'] ?? null,
                'duration' => $meetingData['duration'] ?? null,
                'timestamp' => now()->toISOString()
            ];

            $this->leadScoringService->processEvent($eventData, $contact->tenant_id);

            Log::info('Meeting event processed for lead scoring', [
                'event' => $eventType,
                'contact_id' => $contactId,
                'tenant_id' => $contact->tenant_id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process meeting event for lead scoring', [
                'event' => $eventType,
                'contact_id' => $contactId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Auto-detect events from system activity
     */
    public function autoDetectEvents(int $tenantId): array
    {
        $suggestions = [];
        
        try {
            // Analyze recent activity to suggest rules
            $recentActivity = $this->analyzeRecentActivity($tenantId);
            
            if ($recentActivity['email_opens'] > 10) {
                $suggestions[] = [
                    'type' => 'email_open',
                    'points' => 5,
                    'reason' => 'High email open activity detected',
                    'confidence' => 0.9
                ];
            }
            
            if ($recentActivity['form_submissions'] > 5) {
                $suggestions[] = [
                    'type' => 'form_submission',
                    'points' => 15,
                    'reason' => 'Active form submission activity',
                    'confidence' => 0.95
                ];
            }
            
            if ($recentActivity['page_visits'] > 20) {
                $suggestions[] = [
                    'type' => 'page_visit',
                    'points' => 3,
                    'reason' => 'High website engagement',
                    'confidence' => 0.8
                ];
            }

        } catch (\Exception $e) {
            Log::error('Failed to auto-detect events', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
        }

        return $suggestions;
    }

    /**
     * Analyze recent activity for suggestions
     */
    private function analyzeRecentActivity(int $tenantId): array
    {
        // This would analyze your actual data to suggest rules
        // For now, returning mock data
        return [
            'email_opens' => 25,
            'email_clicks' => 15,
            'form_submissions' => 8,
            'page_visits' => 45,
            'deal_created' => 3,
            'meeting_scheduled' => 5
        ];
    }
}


