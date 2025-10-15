<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\ContactInteraction;
use App\Models\Deal;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\Activity;
use App\Models\FormSubmission;
use App\Models\CampaignRecipient;
use App\Models\EventAttendee;
use App\Models\JourneyExecution;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContactJourneyFlowService
{
    /**
     * Get contact journey flow
     */
    public function getContactJourneyFlow($contactId)
    {
        try {
            $contact = Contact::with([
                'company',
                'interactions',
                'deals',
                'meetings',
                'tasks',
                'activities',
                'formSubmissions',
                'campaignRecipients',
                'eventAttendees',
                'journeyExecutions'
            ])->find($contactId);

            if (!$contact) {
                return [
                    'status' => 'not_found',
                    'current_stage' => 'Contact Not Found',
                    'progress_percentage' => 0,
                    'last_activity' => null,
                    'next_action' => 'Contact does not exist',
                    'timeline_events' => []
                ];
            }

            // Get timeline events
            $timelineEvents = $this->buildTimelineEvents($contact);
            
            // Determine journey status
            $journeyStatus = $this->determineJourneyStatus($contact, $timelineEvents);
            
            // Get current stage
            $currentStage = $this->getCurrentStage($contact, $timelineEvents);
            
            // Calculate progress
            $progressPercentage = $this->calculateProgress($contact, $timelineEvents);
            
            // Get last activity
            $lastActivity = $this->getLastActivity($contact, $timelineEvents);
            
            // Get next action
            $nextAction = $this->getNextAction($contact, $timelineEvents, $currentStage);

            return [
                'status' => $journeyStatus,
                'current_stage' => $currentStage,
                'progress_percentage' => $progressPercentage,
                'last_activity' => $lastActivity,
                'next_action' => $nextAction,
                'timeline_events' => $timelineEvents
            ];

        } catch (\Exception $e) {
            Log::error('Error getting contact journey flow: ' . $e->getMessage());
            return [
                'status' => 'error',
                'current_stage' => 'Error',
                'progress_percentage' => 0,
                'last_activity' => null,
                'next_action' => 'Error occurred',
                'timeline_events' => []
            ];
        }
    }

    /**
     * Build timeline events for contact
     */
    private function buildTimelineEvents($contact)
    {
        $events = [];

        // Contact creation event
        $events[] = [
            'id' => 'contact_created',
            'type' => 'contact_created',
            'title' => 'Contact Created',
            'description' => 'Contact was added to the system',
            'date' => $contact->created_at,
            'icon' => 'user-plus',
            'color' => 'blue',
            'status' => 'completed'
        ];

        // Company association
        if ($contact->company) {
            $events[] = [
                'id' => 'company_associated',
                'type' => 'company_associated',
                'title' => 'Company Associated',
                'description' => "Associated with {$contact->company->name}",
                'date' => $contact->updated_at,
                'icon' => 'building',
                'color' => 'purple',
                'status' => 'completed'
            ];
        }

        // Form submissions
        foreach ($contact->formSubmissions as $submission) {
            $events[] = [
                'id' => "form_submission_{$submission->id}",
                'type' => 'form_submission',
                'title' => 'Form Submitted',
                'description' => "Submitted form: {$submission->form->name}",
                'date' => $submission->created_at,
                'icon' => 'file-text',
                'color' => 'orange',
                'status' => 'completed'
            ];
        }

        // Interactions
        foreach ($contact->interactions as $interaction) {
            $events[] = [
                'id' => "interaction_{$interaction->id}",
                'type' => 'interaction',
                'title' => ucfirst($interaction->type) . ' Interaction',
                'description' => $interaction->message,
                'date' => $interaction->created_at,
                'icon' => $this->getInteractionIcon($interaction->type),
                'color' => $this->getInteractionColor($interaction->type),
                'status' => 'completed'
            ];
        }

        // Deals
        foreach ($contact->deals as $deal) {
            $events[] = [
                'id' => "deal_{$deal->id}",
                'type' => 'deal',
                'title' => 'Deal Created',
                'description' => $deal->title,
                'date' => $deal->created_at,
                'icon' => 'dollar-sign',
                'color' => 'green',
                'status' => 'completed'
            ];
        }

        // Meetings
        foreach ($contact->meetings as $meeting) {
            $events[] = [
                'id' => "meeting_{$meeting->id}",
                'type' => 'meeting',
                'title' => 'Meeting Scheduled',
                'description' => $meeting->title,
                'date' => $meeting->scheduled_at,
                'icon' => 'calendar',
                'color' => 'blue',
                'status' => 'completed'
            ];
        }

        // Campaign activities
        foreach ($contact->campaignRecipients as $recipient) {
            $events[] = [
                'id' => "campaign_{$recipient->id}",
                'type' => 'campaign',
                'title' => 'Campaign Sent',
                'description' => "Received campaign: {$recipient->campaign->name}",
                'date' => $recipient->sent_at ?: $recipient->created_at,
                'icon' => 'mail',
                'color' => 'indigo',
                'status' => 'completed'
            ];
        }

        // Sort events by date
        usort($events, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        return $events;
    }

    /**
     * Determine journey status
     */
    private function determineJourneyStatus($contact, $timelineEvents)
    {
        // Check if contact has any activity
        if (count($timelineEvents) <= 1) { // Only contact creation
            return 'pending';
        }

        // Check if contact has recent activity (within last 7 days)
        $lastActivity = $this->getLastActivity($contact, $timelineEvents);
        if ($lastActivity && Carbon::parse($lastActivity)->diffInDays(now()) <= 7) {
            return 'active';
        }

        // Check if contact has completed key milestones
        $hasFormSubmission = collect($timelineEvents)->contains('type', 'form_submission');
        $hasInteraction = collect($timelineEvents)->contains('type', 'interaction');
        $hasDeal = collect($timelineEvents)->contains('type', 'deal');

        if ($hasDeal) {
            return 'completed';
        }

        if ($hasFormSubmission || $hasInteraction) {
            return 'active';
        }

        return 'pending';
    }

    /**
     * Get current stage
     */
    private function getCurrentStage($contact, $timelineEvents)
    {
        $eventTypes = collect($timelineEvents)->pluck('type')->toArray();

        // Define journey stages
        $stages = [
            'contact_created' => 'Contact Created',
            'company_associated' => 'Company Associated',
            'form_submission' => 'Lead Qualification',
            'interaction' => 'Engagement',
            'deal' => 'Sales Process',
            'meeting' => 'Meeting Scheduled',
            'campaign' => 'Marketing Engagement'
        ];

        // Find the latest stage
        foreach (array_reverse($eventTypes) as $type) {
            if (isset($stages[$type])) {
                return $stages[$type];
            }
        }

        return 'Contact Created';
    }

    /**
     * Calculate progress percentage
     */
    private function calculateProgress($contact, $timelineEvents)
    {
        $totalPossibleEvents = 7; // Contact, Company, Form, Interaction, Deal, Meeting, Campaign
        $completedEvents = count($timelineEvents);

        return min(100, round(($completedEvents / $totalPossibleEvents) * 100, 1));
    }

    /**
     * Get last activity
     */
    private function getLastActivity($contact, $timelineEvents)
    {
        if (empty($timelineEvents)) {
            return $contact->last_activity?->toIso8601String();
        }

        $lastEvent = end($timelineEvents);
        return $lastEvent['date']->toIso8601String();
    }

    /**
     * Get next action
     */
    private function getNextAction($contact, $timelineEvents, $currentStage)
    {
        $eventTypes = collect($timelineEvents)->pluck('type')->toArray();

        if (!in_array('form_submission', $eventTypes)) {
            return 'Send welcome email and encourage form submission';
        }

        if (!in_array('interaction', $eventTypes)) {
            return 'Schedule a call or meeting to engage with the contact';
        }

        if (!in_array('deal', $eventTypes)) {
            return 'Create a deal opportunity in the sales pipeline';
        }

        if (!in_array('meeting', $eventTypes)) {
            return 'Schedule a discovery call or demo meeting';
        }

        return 'Continue nurturing the relationship';
    }

    /**
     * Get journey flow summary
     */
    public function getJourneyFlowSummary()
    {
        try {
            $totalContacts = Contact::count();
            $contactsWithInteractions = Contact::whereHas('interactions')->count();
            $contactsWithDeals = Contact::whereHas('deals')->count();
            $contactsWithMeetings = Contact::whereHas('meetings')->count();
            $contactsWithFormSubmissions = Contact::whereHas('formSubmissions')->count();

            $recentContacts = Contact::where('created_at', '>=', now()->subDays(30))->count();
            $activeContacts = Contact::where('last_activity', '>=', now()->subDays(7))->count();

            return [
                'total_contacts' => $totalContacts,
                'contacts_with_interactions' => $contactsWithInteractions,
                'contacts_with_deals' => $contactsWithDeals,
                'contacts_with_meetings' => $contactsWithMeetings,
                'contacts_with_form_submissions' => $contactsWithFormSubmissions,
                'recent_contacts_30_days' => $recentContacts,
                'active_contacts_7_days' => $activeContacts,
                'engagement_rate' => $totalContacts > 0 ? round(($contactsWithInteractions / $totalContacts) * 100, 1) : 0,
                'conversion_rate' => $totalContacts > 0 ? round(($contactsWithDeals / $totalContacts) * 100, 1) : 0
            ];

        } catch (\Exception $e) {
            Log::error('Error getting journey flow summary: ' . $e->getMessage());
            return [
                'total_contacts' => 0,
                'contacts_with_interactions' => 0,
                'contacts_with_deals' => 0,
                'contacts_with_meetings' => 0,
                'contacts_with_form_submissions' => 0,
                'recent_contacts_30_days' => 0,
                'active_contacts_7_days' => 0,
                'engagement_rate' => 0,
                'conversion_rate' => 0
            ];
        }
    }

    /**
     * Get interaction icon
     */
    private function getInteractionIcon($type)
    {
        $icons = [
            'email' => 'mail',
            'call' => 'phone',
            'meeting' => 'calendar',
            'note' => 'file-text',
            'task' => 'check-square',
            'website_visit' => 'globe',
            'form_submission' => 'file-text',
            'campaign' => 'mail',
            'download' => 'download',
        ];

        return $icons[$type] ?? 'activity';
    }

    /**
     * Get interaction color
     */
    private function getInteractionColor($type)
    {
        $colors = [
            'email' => 'blue',
            'call' => 'green',
            'meeting' => 'purple',
            'note' => 'gray',
            'task' => 'orange',
            'website_visit' => 'indigo',
            'form_submission' => 'orange',
            'campaign' => 'indigo',
            'download' => 'teal',
        ];

        return $colors[$type] ?? 'gray';
    }
}
