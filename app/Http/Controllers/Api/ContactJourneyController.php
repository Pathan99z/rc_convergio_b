<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
use App\Events\ContactJourneyUpdated;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ContactJourneyController extends Controller
{
    /**
     * Get contact journey by email
     */
    public function getByEmail($email)
    {
        try {
            $contact = Contact::where('email', $email)->firstOrFail();
            
            // Get interactions ordered by date (newest first)
            $interactions = $contact->interactions()
                ->orderBy('created_at', 'desc')
                ->get();
            
            // Get engagement metrics
            $metrics = $this->calculateEngagementMetrics($contact->id);
            
            // Prepare recent activities (last 10 interactions)
            $recentActivities = $interactions->take(10)->map(function($interaction) {
                return [
                    'id' => $interaction->id,
                    'type' => $interaction->type,
                    'message' => $interaction->message,
                    'details' => $interaction->details,
                    'metadata' => $interaction->metadata,
                    'created_at' => $interaction->created_at->toIso8601String()
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'contact' => [
                        'id' => $contact->id,
                        'name' => $contact->full_name,
                        'email' => $contact->email,
                        'phone' => $contact->phone,
                        'company' => $contact->company ? $contact->company->name : null,
                        'status' => $contact->lifecycle_stage,
                        'created_at' => $contact->created_at->toIso8601String(),
                        'updated_at' => $contact->updated_at->toIso8601String()
                    ],
                    'events' => $interactions->map(function($interaction) {
                        return [
                            'id' => $interaction->id,
                            'type' => $interaction->type,
                            'message' => $interaction->message,
                            'details' => $interaction->details,
                            'metadata' => $interaction->metadata,
                            'created_at' => $interaction->created_at->toIso8601String()
                        ];
                    }),
                    'metrics' => $metrics,
                    'recentActivities' => $recentActivities
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching contact journey by email: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Contact not found',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 404);
        }
    }

    /**
     * Get contact journey by ID
     */
    public function getById($id)
    {
        try {
            $contact = Contact::findOrFail($id);
            
            // Get interactions ordered by date (newest first)
            $interactions = $contact->interactions()
                ->orderBy('created_at', 'desc')
                ->get();
            
            // Get engagement metrics
            $metrics = $this->calculateEngagementMetrics($contact->id);
            
            // Prepare recent activities (last 10 interactions)
            $recentActivities = $interactions->take(10)->map(function($interaction) {
                return [
                    'id' => $interaction->id,
                    'type' => $interaction->type,
                    'message' => $interaction->message,
                    'details' => $interaction->details,
                    'metadata' => $interaction->metadata,
                    'created_at' => $interaction->created_at->toIso8601String()
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'contact' => [
                        'id' => $contact->id,
                        'name' => $contact->full_name,
                        'email' => $contact->email,
                        'phone' => $contact->phone,
                        'company' => $contact->company ? $contact->company->name : null,
                        'status' => $contact->lifecycle_stage,
                        'created_at' => $contact->created_at->toIso8601String(),
                        'updated_at' => $contact->updated_at->toIso8601String()
                    ],
                    'events' => $interactions->map(function($interaction) {
                        return [
                            'id' => $interaction->id,
                            'type' => $interaction->type,
                            'message' => $interaction->message,
                            'details' => $interaction->details,
                            'metadata' => $interaction->metadata,
                            'created_at' => $interaction->created_at->toIso8601String()
                        ];
                    }),
                    'metrics' => $metrics,
                    'recentActivities' => $recentActivities
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching contact journey by ID: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Contact not found',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 404);
        }
    }

    /**
     * Get recent contacts with their latest interaction
     */
    public function getRecentContacts()
    {
        try {
            $contacts = Contact::with(['latestInteraction'])
                ->orderBy('last_activity', 'desc')
                ->take(20)
                ->get()
                ->map(function($contact) {
                    $latestInteraction = $contact->latestInteraction;
                    
                    return [
                        'id' => $contact->id,
                        'name' => $contact->full_name,
                        'email' => $contact->email,
                        'phone' => $contact->phone,
                        'company' => $contact->company ? $contact->company->name : null,
                        'status' => $contact->lifecycle_stage,
                        'last_activity' => $contact->last_activity ? $contact->last_activity->toIso8601String() : null,
                        'latest_interaction' => $latestInteraction ? [
                            'id' => $latestInteraction->id,
                            'type' => $latestInteraction->type,
                            'message' => $latestInteraction->message,
                            'created_at' => $latestInteraction->created_at->toIso8601String()
                        ] : null
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $contacts
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching recent contacts: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching recent contacts',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Add interaction to contact and broadcast the update
     */
    public function addInteraction(Request $request, $contactId)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|string|max:50',
            'message' => 'nullable|string|max:255',
            'details' => 'nullable|string',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $contact = Contact::findOrFail($contactId);
            
            $interaction = $contact->interactions()->create([
                'type' => $request->type,
                'message' => $request->message,
                'details' => $request->details,
                'metadata' => $request->metadata,
            ]);

            // Update contact's last activity
            $contact->update([
                'last_activity' => now()
            ]);

            // Calculate engagement metrics
            $metrics = $this->calculateEngagementMetrics($contact->id);

            // Broadcast the update
            event(new ContactJourneyUpdated($contact->id, [
                'interaction' => $interaction,
                'metrics' => $metrics
            ]));

            return response()->json([
                'success' => true,
                'data' => [
                    'interaction' => $interaction,
                    'metrics' => $metrics
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error adding interaction: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error adding interaction',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Calculate engagement metrics for a contact
     */
    protected function calculateEngagementMetrics($contactId)
    {
        $interactions = ContactInteraction::where('contact_id', $contactId)
            ->select('type', 'created_at')
            ->get();

        $totalInteractions = $interactions->count();
        $emailInteractions = $interactions->where('type', 'email');
        $totalEmails = $emailInteractions->count();
        $openedEmails = $emailInteractions->filter(function ($interaction) {
            return isset($interaction->metadata['opened']) && $interaction->metadata['opened'] === true;
        })->count();
        $clickedEmails = $emailInteractions->filter(function ($interaction) {
            return isset($interaction->metadata['clicked']) && $interaction->metadata['clicked'] === true;
        })->count();

        return [
            'total_interactions' => $totalInteractions,
            'email_open_rate' => $totalEmails > 0 ? round(($openedEmails / $totalEmails) * 100) : 0,
            'email_click_rate' => $totalEmails > 0 ? round(($clickedEmails / $totalEmails) * 100) : 0,
            'last_activity' => $interactions->isNotEmpty() 
                ? $interactions->sortByDesc('created_at')->first()->created_at->toIso8601String()
                : null,
            'interactions_by_type' => $interactions->groupBy('type')->map->count()
        ];
    }

    /**
     * Get engagement metrics for a contact
     */
    public function getEngagementMetrics($contactId)
    {
        try {
            $metrics = $this->calculateEngagementMetrics($contactId);
            
            // Add additional metrics specific to this endpoint
            $interactions = ContactInteraction::where('contact_id', $contactId)
                ->select('created_at')
                ->get();
                
            $metrics['last_30_days'] = $interactions->where('created_at', '>=', now()->subDays(30))->count();
            
            if ($interactions->isNotEmpty()) {
                $metrics['first_interaction'] = $interactions->sortBy('created_at')->first()->created_at;
                $metrics['last_interaction'] = $interactions->sortByDesc('created_at')->first()->created_at;
            } else {
                $metrics['first_interaction'] = null;
                $metrics['last_interaction'] = null;
                $metrics['last_30_days'] = 0;
            }

            return response()->json([
                'success' => true,
                'data' => $metrics
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching engagement metrics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching engagement metrics',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get comprehensive journey flow for a contact including company and deals
     */
    public function getJourneyFlow($contactId)
    {
        try {
            $contact = Contact::with([
                'company',
                'owner',
                'deals.stage',
                'deals.pipeline',
                'meetings',
                'tasks',
                'activities',
                'formSubmissions.form',
                'campaignRecipients.campaign',
                'eventAttendees.event',
                'journeyExecutions.journey',
                'interactions'
            ])->findOrFail($contactId);

            // Collect all timeline events
            $timelineEvents = collect();

            // Add contact creation
            $timelineEvents->push([
                'id' => 'contact_created',
                'type' => 'contact_created',
                'title' => 'Contact Created',
                'description' => 'Contact was added to the system',
                'date' => $contact->created_at,
                'icon' => 'user-plus',
                'color' => 'blue',
                'data' => [
                    'source' => $contact->source,
                    'lifecycle_stage' => $contact->lifecycle_stage,
                ]
            ]);

            // Add company association if exists
            if ($contact->company) {
                $timelineEvents->push([
                    'id' => 'company_associated',
                    'type' => 'company_associated',
                    'title' => 'Associated with Company',
                    'description' => "Associated with {$contact->company->name}",
                    'date' => $contact->updated_at,
                    'icon' => 'building',
                    'color' => 'purple',
                    'data' => [
                        'company_name' => $contact->company->name,
                        'company_industry' => $contact->company->industry,
                        'company_size' => $contact->company->size,
                    ]
                ]);
            }

            // Add interactions
            foreach ($contact->interactions as $interaction) {
                $timelineEvents->push([
                    'id' => "interaction_{$interaction->id}",
                    'type' => 'interaction',
                    'title' => ucfirst($interaction->type) . ' Interaction',
                    'description' => $interaction->message,
                    'date' => $interaction->created_at,
                    'icon' => $this->getInteractionIcon($interaction->type),
                    'color' => $this->getInteractionColor($interaction->type),
                    'data' => [
                        'interaction_type' => $interaction->type,
                        'details' => $interaction->details,
                        'metadata' => $interaction->metadata,
                    ]
                ]);
            }

            // Add deals
            foreach ($contact->deals as $deal) {
                $timelineEvents->push([
                    'id' => "deal_{$deal->id}",
                    'type' => 'deal',
                    'title' => 'Deal Created',
                    'description' => $deal->title,
                    'date' => $deal->created_at,
                    'icon' => 'dollar-sign',
                    'color' => 'green',
                    'data' => [
                        'deal_value' => $deal->value,
                        'deal_status' => $deal->status,
                        'stage' => $deal->stage?->name,
                        'pipeline' => $deal->pipeline?->name,
                        'probability' => $deal->probability,
                        'expected_close_date' => $deal->expected_close_date,
                    ]
                ]);

                // Add deal status changes if closed
                if ($deal->closed_date) {
                    $timelineEvents->push([
                        'id' => "deal_closed_{$deal->id}",
                        'type' => 'deal_closed',
                        'title' => 'Deal ' . ucfirst($deal->status),
                        'description' => "Deal '{$deal->title}' was {$deal->status}",
                        'date' => $deal->closed_date,
                        'icon' => $deal->status === 'won' ? 'check-circle' : 'x-circle',
                        'color' => $deal->status === 'won' ? 'green' : 'red',
                        'data' => [
                            'deal_value' => $deal->value,
                            'close_reason' => $deal->close_reason,
                        ]
                    ]);
                }
            }

            // Add meetings
            foreach ($contact->meetings as $meeting) {
                $timelineEvents->push([
                    'id' => "meeting_{$meeting->id}",
                    'type' => 'meeting',
                    'title' => 'Meeting Scheduled',
                    'description' => $meeting->title,
                    'date' => $meeting->scheduled_at,
                    'icon' => 'calendar',
                    'color' => 'blue',
                    'data' => [
                        'duration' => $meeting->duration_minutes,
                        'location' => $meeting->location,
                        'status' => $meeting->status,
                        'attendees' => $meeting->attendees,
                    ]
                ]);

                if ($meeting->completed_at) {
                    $timelineEvents->push([
                        'id' => "meeting_completed_{$meeting->id}",
                        'type' => 'meeting_completed',
                        'title' => 'Meeting Completed',
                        'description' => "Meeting '{$meeting->title}' was completed",
                        'date' => $meeting->completed_at,
                        'icon' => 'check-circle',
                        'color' => 'green',
                        'data' => [
                            'notes' => $meeting->notes,
                        ]
                    ]);
                }
            }

            // Add form submissions
            foreach ($contact->formSubmissions as $submission) {
                $timelineEvents->push([
                    'id' => "form_submission_{$submission->id}",
                    'type' => 'form_submission',
                    'title' => 'Form Submitted',
                    'description' => "Submitted form: {$submission->form->name}",
                    'date' => $submission->created_at,
                    'icon' => 'file-text',
                    'color' => 'orange',
                    'data' => [
                        'form_name' => $submission->form->name,
                        'payload' => $submission->payload,
                        'status' => $submission->status,
                    ]
                ]);
            }

            // Add campaign activities
            foreach ($contact->campaignRecipients as $recipient) {
                $timelineEvents->push([
                    'id' => "campaign_{$recipient->id}",
                    'type' => 'campaign',
                    'title' => 'Campaign Sent',
                    'description' => "Received campaign: {$recipient->campaign->name}",
                    'date' => $recipient->sent_at ?: $recipient->created_at,
                    'icon' => 'mail',
                    'color' => 'indigo',
                    'data' => [
                        'campaign_name' => $recipient->campaign->name,
                        'campaign_type' => $recipient->campaign->type,
                        'status' => $recipient->status,
                        'opened_at' => $recipient->opened_at,
                        'clicked_at' => $recipient->clicked_at,
                    ]
                ]);
            }

            // Add event attendance
            foreach ($contact->eventAttendees as $attendee) {
                $timelineEvents->push([
                    'id' => "event_{$attendee->id}",
                    'type' => 'event',
                    'title' => 'Event Registration',
                    'description' => "Registered for event: {$attendee->event->name}",
                    'date' => $attendee->registered_at ?: $attendee->created_at,
                    'icon' => 'calendar-check',
                    'color' => 'teal',
                    'data' => [
                        'event_name' => $attendee->event->name,
                        'event_type' => $attendee->event->type,
                        'status' => $attendee->status,
                        'attended_at' => $attendee->attended_at,
                    ]
                ]);
            }

            // Add journey executions
            foreach ($contact->journeyExecutions as $execution) {
                $timelineEvents->push([
                    'id' => "journey_{$execution->id}",
                    'type' => 'journey',
                    'title' => 'Journey Started',
                    'description' => "Started journey: {$execution->journey->name}",
                    'date' => $execution->started_at,
                    'icon' => 'map',
                    'color' => 'purple',
                    'data' => [
                        'journey_name' => $execution->journey->name,
                        'status' => $execution->status,
                        'current_step' => $execution->currentStep?->name,
                        'completed_at' => $execution->completed_at,
                    ]
                ]);
            }

            // Sort timeline by date (most recent first)
            $sortedTimeline = $timelineEvents->sortByDesc('date')->values();

            // Calculate journey metrics
            $metrics = $this->calculateEngagementMetrics($contactId);
            
            // Add journey-specific metrics
            $journeyMetrics = [
                'total_timeline_events' => $sortedTimeline->count(),
                'deals_count' => $contact->deals->count(),
                'deals_value' => $contact->deals->sum('value'),
                'meetings_count' => $contact->meetings->count(),
                'forms_submitted' => $contact->formSubmissions->count(),
                'campaigns_received' => $contact->campaignRecipients->count(),
                'events_attended' => $contact->eventAttendees->count(),
                'active_journeys' => $contact->journeyExecutions->whereIn('status', ['running', 'paused'])->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'contact' => [
                        'id' => $contact->id,
                        'full_name' => $contact->full_name,
                        'email' => $contact->email,
                        'phone' => $contact->phone,
                        'lifecycle_stage' => $contact->lifecycle_stage,
                        'lead_score' => $contact->lead_score,
                        'source' => $contact->source,
                        'tags' => $contact->tags,
                        'created_at' => $contact->created_at->toIso8601String(),
                        'last_activity' => $contact->last_activity?->toIso8601String(),
                    ],
                    'company' => $contact->company ? [
                        'id' => $contact->company->id,
                        'name' => $contact->company->name,
                        'industry' => $contact->company->industry,
                        'size' => $contact->company->size,
                        'website' => $contact->company->website,
                        'annual_revenue' => $contact->company->annual_revenue,
                    ] : null,
                    'timeline' => $sortedTimeline,
                    'metrics' => array_merge($metrics, $journeyMetrics),
                    'summary' => [
                        'total_events' => $sortedTimeline->count(),
                        'first_interaction' => $sortedTimeline->last()['date'] ?? null,
                        'last_interaction' => $sortedTimeline->first()['date'] ?? null,
                        'event_types' => $sortedTimeline->groupBy('type')->map->count(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching journey flow: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching journey flow',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get icon for interaction type
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
     * Get step-by-step journey progress for a contact
     */
    public function getJourneyProgress($contactId)
    {
        try {
            $contact = Contact::with(['company'])->findOrFail($contactId);
            
            // Get all journey executions for this contact
            $journeyExecutions = JourneyExecution::with(['journey.steps', 'currentStep'])
                ->where('contact_id', $contactId)
                ->get();

            if ($journeyExecutions->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'contact' => [
                            'id' => $contact->id,
                            'full_name' => $contact->full_name,
                            'email' => $contact->email,
                            'company' => $contact->company?->name,
                        ],
                        'journeys' => [],
                        'message' => 'No active journeys found for this contact'
                    ]
                ]);
            }

            $journeyProgress = [];

            foreach ($journeyExecutions as $execution) {
                $journey = $execution->journey;
                $steps = $journey->steps()->orderBy('order_no')->get();
                $currentStepId = $execution->current_step_id;
                $executionData = $execution->execution_data ?? [];
                $completedSteps = $executionData['completed_steps'] ?? [];

                $stepsProgress = [];
                $currentStepFound = false;

                foreach ($steps as $step) {
                    $stepStatus = 'pending';
                    $completedAt = null;
                    $startedAt = null;

                    // Determine step status
                    if (in_array($step->id, $completedSteps)) {
                        $stepStatus = 'completed';
                        $completedAt = $executionData['step_completed_' . $step->id] ?? null;
                    } elseif ($step->id == $currentStepId) {
                        $stepStatus = 'in_progress';
                        $currentStepFound = true;
                        $startedAt = $executionData['current_step_started_at'] ?? $execution->created_at;
                    } elseif ($currentStepFound) {
                        $stepStatus = 'pending';
                    } else {
                        // This step comes before current step, so it should be completed
                        $stepStatus = 'completed';
                        $completedAt = $executionData['step_completed_' . $step->id] ?? null;
                    }

                    $stepsProgress[] = [
                        'id' => $step->id,
                        'order_no' => $step->order_no,
                        'step_type' => $step->step_type,
                        'title' => $this->getStepTitle($step),
                        'description' => $step->getDescription(),
                        'status' => $stepStatus,
                        'started_at' => $startedAt,
                        'completed_at' => $completedAt,
                        'config' => $step->config,
                        'icon' => $this->getStepIcon($step->step_type),
                        'color' => $this->getStepColor($stepStatus),
                        'is_current' => $step->id == $currentStepId,
                        'progress_percentage' => $this->calculateStepProgress($step, $stepStatus, $execution)
                    ];
                }

                // Calculate overall journey progress
                $totalSteps = count($steps);
                $completedStepsCount = count(array_filter($stepsProgress, fn($s) => $s['status'] === 'completed'));
                $inProgressStepsCount = count(array_filter($stepsProgress, fn($s) => $s['status'] === 'in_progress'));
                
                $overallProgress = $totalSteps > 0 ? 
                    round((($completedStepsCount + ($inProgressStepsCount * 0.5)) / $totalSteps) * 100, 1) : 0;

                $journeyProgress[] = [
                    'journey_id' => $journey->id,
                    'journey_name' => $journey->name,
                    'journey_description' => $journey->description,
                    'execution_id' => $execution->id,
                    'status' => $execution->status,
                    'started_at' => $execution->started_at,
                    'completed_at' => $execution->completed_at,
                    'next_step_at' => $execution->next_step_at,
                    'overall_progress_percentage' => $overallProgress,
                    'current_step_order' => $execution->currentStep?->order_no ?? 1,
                    'total_steps' => $totalSteps,
                    'completed_steps_count' => $completedStepsCount,
                    'steps' => $stepsProgress,
                    'timeline_summary' => $this->generateTimelineSummary($stepsProgress)
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'contact' => [
                        'id' => $contact->id,
                        'full_name' => $contact->full_name,
                        'email' => $contact->email,
                        'phone' => $contact->phone,
                        'lifecycle_stage' => $contact->lifecycle_stage,
                        'lead_score' => $contact->lead_score,
                        'company' => $contact->company ? [
                            'id' => $contact->company->id,
                            'name' => $contact->company->name,
                            'industry' => $contact->company->industry,
                        ] : null,
                    ],
                    'journeys' => $journeyProgress,
                    'summary' => [
                        'total_journeys' => count($journeyProgress),
                        'active_journeys' => count(array_filter($journeyProgress, fn($j) => $j['status'] === 'running')),
                        'completed_journeys' => count(array_filter($journeyProgress, fn($j) => $j['status'] === 'completed')),
                        'average_progress' => count($journeyProgress) > 0 ? 
                            round(array_sum(array_column($journeyProgress, 'overall_progress_percentage')) / count($journeyProgress), 1) : 0
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching journey progress: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching journey progress',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get step title for display
     */
    private function getStepTitle($step)
    {
        $titles = [
            'send_email' => 'Send Email',
            'wait' => 'Wait Period',
            'create_task' => 'Create Task',
            'update_contact' => 'Update Contact',
            'create_deal' => 'Create Deal',
            'update_deal' => 'Update Deal',
            'add_tag' => 'Add Tags',
            'remove_tag' => 'Remove Tags',
            'update_lead_score' => 'Update Lead Score',
            'send_sms' => 'Send SMS',
            'webhook' => 'Webhook Call',
            'condition' => 'Conditional Check',
            'end' => 'Journey Complete',
        ];

        $baseTitle = $titles[$step->step_type] ?? ucfirst(str_replace('_', ' ', $step->step_type));
        
        // Add specific details based on config
        switch ($step->step_type) {
            case 'send_email':
                return $baseTitle . (isset($step->config['subject']) ? ': ' . $step->config['subject'] : '');
            case 'create_task':
                return $baseTitle . (isset($step->config['title']) ? ': ' . $step->config['title'] : '');
            case 'create_deal':
                return $baseTitle . (isset($step->config['name']) ? ': ' . $step->config['name'] : '');
            case 'wait':
                $days = $step->config['days'] ?? 0;
                $hours = $step->config['hours'] ?? 0;
                return $baseTitle . " ({$days}d {$hours}h)";
            default:
                return $baseTitle;
        }
    }

    /**
     * Get step icon
     */
    private function getStepIcon($stepType)
    {
        $icons = [
            'send_email' => 'mail',
            'wait' => 'clock',
            'create_task' => 'check-square',
            'update_contact' => 'user-edit',
            'create_deal' => 'dollar-sign',
            'update_deal' => 'trending-up',
            'add_tag' => 'tag',
            'remove_tag' => 'tag',
            'update_lead_score' => 'star',
            'send_sms' => 'message-square',
            'webhook' => 'link',
            'condition' => 'git-branch',
            'end' => 'flag',
        ];

        return $icons[$stepType] ?? 'circle';
    }

    /**
     * Get step color based on status
     */
    private function getStepColor($status)
    {
        $colors = [
            'completed' => 'green',
            'in_progress' => 'blue',
            'pending' => 'gray',
            'failed' => 'red',
            'cancelled' => 'orange',
        ];

        return $colors[$status] ?? 'gray';
    }

    /**
     * Calculate step progress percentage
     */
    private function calculateStepProgress($step, $status, $execution)
    {
        switch ($status) {
            case 'completed':
                return 100;
            case 'in_progress':
                // For wait steps, calculate based on time elapsed
                if ($step->step_type === 'wait') {
                    $config = $step->config;
                    $totalMinutes = ($config['days'] ?? 0) * 24 * 60 + 
                                   ($config['hours'] ?? 0) * 60 + 
                                   ($config['minutes'] ?? 0);
                    
                    if ($totalMinutes > 0 && $execution->next_step_at) {
                        $startTime = $execution->updated_at;
                        $endTime = $execution->next_step_at;
                        $currentTime = now();
                        
                        if ($currentTime >= $endTime) {
                            return 100;
                        }
                        
                        $elapsed = $startTime->diffInMinutes($currentTime);
                        return min(100, round(($elapsed / $totalMinutes) * 100, 1));
                    }
                }
                return 50; // Default for in-progress steps
            case 'pending':
                return 0;
            default:
                return 0;
        }
    }

    /**
     * Generate timeline summary
     */
    private function generateTimelineSummary($steps)
    {
        $completed = array_filter($steps, fn($s) => $s['status'] === 'completed');
        $inProgress = array_filter($steps, fn($s) => $s['status'] === 'in_progress');
        $pending = array_filter($steps, fn($s) => $s['status'] === 'pending');

        return [
            'completed_count' => count($completed),
            'in_progress_count' => count($inProgress),
            'pending_count' => count($pending),
            'next_step' => !empty($pending) ? reset($pending)['title'] : 'Journey Complete',
            'last_completed' => !empty($completed) ? end($completed)['title'] : 'None',
            'current_step' => !empty($inProgress) ? reset($inProgress)['title'] : 'None'
        ];
    }

    /**
     * Get professional journey timeline with achieved/pending status
     */
    public function getJourneyTimeline($contactId)
    {
        try {
            $contact = Contact::with(['company', 'interactions'])->findOrFail($contactId);
            
            // Get all interactions for this contact
            $interactions = $contact->interactions()
                ->orderBy('created_at', 'desc')
                ->get();

            // Define the standard journey flow steps
            $journeySteps = [
                [
                    'id' => 'form_submission',
                    'title' => 'Initial Inquiry',
                    'description' => 'Contact submitted inquiry form',
                    'type' => 'form_submission',
                    'icon' => 'file-text',
                    'color' => 'blue',
                    'order' => 1,
                    'category' => 'Lead Generation'
                ],
                [
                    'id' => 'welcome_email',
                    'title' => 'Welcome Email',
                    'description' => 'Welcome email sent and engagement tracked',
                    'type' => 'email',
                    'icon' => 'mail',
                    'color' => 'green',
                    'order' => 2,
                    'category' => 'Initial Engagement'
                ],
                [
                    'id' => 'enterprise_welcome',
                    'title' => 'Enterprise Welcome',
                    'description' => 'Specialized enterprise welcome email sent',
                    'type' => 'email',
                    'icon' => 'mail-open',
                    'color' => 'purple',
                    'order' => 3,
                    'category' => 'Enterprise Onboarding'
                ],
                [
                    'id' => 'discovery_task',
                    'title' => 'Discovery Call Scheduled',
                    'description' => 'Task created to schedule discovery call',
                    'type' => 'task',
                    'icon' => 'calendar',
                    'color' => 'orange',
                    'order' => 4,
                    'category' => 'Sales Process'
                ],
                [
                    'id' => 'discovery_call',
                    'title' => 'Discovery Call',
                    'description' => 'Conduct discovery call to understand requirements',
                    'type' => 'call',
                    'icon' => 'phone',
                    'color' => 'indigo',
                    'order' => 5,
                    'category' => 'Sales Process'
                ],
                [
                    'id' => 'proposal_creation',
                    'title' => 'Proposal Created',
                    'description' => 'Custom proposal prepared for enterprise needs',
                    'type' => 'document',
                    'icon' => 'file-check',
                    'color' => 'teal',
                    'order' => 6,
                    'category' => 'Proposal Phase'
                ],
                [
                    'id' => 'deal_creation',
                    'title' => 'Deal Created',
                    'description' => 'Opportunity created in sales pipeline',
                    'type' => 'deal',
                    'icon' => 'dollar-sign',
                    'color' => 'yellow',
                    'order' => 7,
                    'category' => 'Pipeline Management'
                ],
                [
                    'id' => 'negotiation',
                    'title' => 'Negotiation Phase',
                    'description' => 'Contract terms and pricing negotiation',
                    'type' => 'meeting',
                    'icon' => 'handshake',
                    'color' => 'red',
                    'order' => 8,
                    'category' => 'Closing Process'
                ],
                [
                    'id' => 'contract_signed',
                    'title' => 'Contract Signed',
                    'description' => 'Agreement finalized and contract executed',
                    'type' => 'contract',
                    'icon' => 'file-signature',
                    'color' => 'green',
                    'order' => 9,
                    'category' => 'Deal Closure'
                ],
                [
                    'id' => 'onboarding_complete',
                    'title' => 'Customer Onboarding',
                    'description' => 'Customer successfully onboarded and activated',
                    'type' => 'success',
                    'icon' => 'check-circle',
                    'color' => 'emerald',
                    'order' => 10,
                    'category' => 'Customer Success'
                ]
            ];

            // Map interactions to journey steps
            $timelineSteps = [];
            foreach ($journeySteps as $step) {
                $matchingInteractions = $this->findMatchingInteractions($interactions, $step);
                
                $stepData = [
                    'id' => $step['id'],
                    'title' => $step['title'],
                    'description' => $step['description'],
                    'type' => $step['type'],
                    'icon' => $step['icon'],
                    'color' => $step['color'],
                    'order' => $step['order'],
                    'category' => $step['category'],
                    'status' => $matchingInteractions->isNotEmpty() ? 'achieved' : 'pending',
                    'achieved_at' => $matchingInteractions->isNotEmpty() ? $matchingInteractions->first()->created_at : null,
                    'interactions' => $matchingInteractions->map(function($interaction) {
                        return [
                            'id' => $interaction->id,
                            'type' => $interaction->type,
                            'message' => $interaction->message,
                            'details' => $interaction->details,
                            'metadata' => $interaction->metadata,
                            'created_at' => $interaction->created_at->toIso8601String(),
                        ];
                    }),
                    'completion_percentage' => $matchingInteractions->isNotEmpty() ? 100 : 0,
                ];

                $timelineSteps[] = $stepData;
            }

            // Calculate overall progress
            $achievedSteps = collect($timelineSteps)->where('status', 'achieved')->count();
            $totalSteps = count($timelineSteps);
            $overallProgress = $totalSteps > 0 ? round(($achievedSteps / $totalSteps) * 100, 1) : 0;

            // Group steps by category for better organization
            $categorizedSteps = collect($timelineSteps)->groupBy('category');

            // Create journey phases
            $journeyPhases = [
                'Lead Generation' => [
                    'title' => 'Lead Generation',
                    'description' => 'Initial contact and interest capture',
                    'icon' => 'target',
                    'color' => 'blue',
                    'steps' => $categorizedSteps->get('Lead Generation', collect())->toArray()
                ],
                'Initial Engagement' => [
                    'title' => 'Initial Engagement', 
                    'description' => 'First touchpoint and welcome process',
                    'icon' => 'mail',
                    'color' => 'green',
                    'steps' => $categorizedSteps->get('Initial Engagement', collect())->toArray()
                ],
                'Enterprise Onboarding' => [
                    'title' => 'Enterprise Onboarding',
                    'description' => 'Specialized enterprise customer journey',
                    'icon' => 'building',
                    'color' => 'purple',
                    'steps' => $categorizedSteps->get('Enterprise Onboarding', collect())->toArray()
                ],
                'Sales Process' => [
                    'title' => 'Sales Process',
                    'description' => 'Discovery and qualification phase',
                    'icon' => 'trending-up',
                    'color' => 'orange',
                    'steps' => $categorizedSteps->get('Sales Process', collect())->toArray()
                ],
                'Proposal Phase' => [
                    'title' => 'Proposal Phase',
                    'description' => 'Solution design and proposal creation',
                    'icon' => 'file-text',
                    'color' => 'teal',
                    'steps' => $categorizedSteps->get('Proposal Phase', collect())->toArray()
                ],
                'Pipeline Management' => [
                    'title' => 'Pipeline Management',
                    'description' => 'Opportunity tracking and management',
                    'icon' => 'bar-chart',
                    'color' => 'yellow',
                    'steps' => $categorizedSteps->get('Pipeline Management', collect())->toArray()
                ],
                'Closing Process' => [
                    'title' => 'Closing Process',
                    'description' => 'Negotiation and deal finalization',
                    'icon' => 'handshake',
                    'color' => 'red',
                    'steps' => $categorizedSteps->get('Closing Process', collect())->toArray()
                ],
                'Deal Closure' => [
                    'title' => 'Deal Closure',
                    'description' => 'Contract execution and completion',
                    'icon' => 'check-square',
                    'color' => 'green',
                    'steps' => $categorizedSteps->get('Deal Closure', collect())->toArray()
                ],
                'Customer Success' => [
                    'title' => 'Customer Success',
                    'description' => 'Onboarding and customer activation',
                    'icon' => 'users',
                    'color' => 'emerald',
                    'steps' => $categorizedSteps->get('Customer Success', collect())->toArray()
                ]
            ];

            // Remove empty phases
            $journeyPhases = array_filter($journeyPhases, function($phase) {
                return !empty($phase['steps']);
            });

            // Calculate phase completion
            foreach ($journeyPhases as &$phase) {
                $phaseAchieved = collect($phase['steps'])->where('status', 'achieved')->count();
                $phaseTotal = count($phase['steps']);
                $phase['completion_percentage'] = $phaseTotal > 0 ? round(($phaseAchieved / $phaseTotal) * 100, 1) : 0;
                $phase['status'] = $phase['completion_percentage'] == 100 ? 'completed' : 
                                 ($phase['completion_percentage'] > 0 ? 'in_progress' : 'pending');
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'contact' => [
                        'id' => $contact->id,
                        'full_name' => $contact->full_name,
                        'email' => $contact->email,
                        'phone' => $contact->phone,
                        'lifecycle_stage' => $contact->lifecycle_stage,
                        'lead_score' => $contact->lead_score,
                        'company' => $contact->company ? [
                            'id' => $contact->company->id,
                            'name' => $contact->company->name,
                            'industry' => $contact->company->industry,
                            'size' => $contact->company->size,
                        ] : null,
                    ],
                    'journey_phases' => $journeyPhases,
                    'timeline_steps' => $timelineSteps,
                    'progress_summary' => [
                        'overall_progress_percentage' => $overallProgress,
                        'achieved_steps' => $achievedSteps,
                        'total_steps' => $totalSteps,
                        'pending_steps' => $totalSteps - $achievedSteps,
                        'current_phase' => $this->getCurrentPhase($journeyPhases),
                        'next_step' => $this->getNextStep($timelineSteps),
                    ],
                    'recent_achievements' => collect($timelineSteps)
                        ->where('status', 'achieved')
                        ->sortByDesc('achieved_at')
                        ->take(5)
                        ->values()
                        ->toArray(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching journey timeline: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching journey timeline',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Find matching interactions for a journey step
     */
    private function findMatchingInteractions($interactions, $step)
    {
        return $interactions->filter(function($interaction) use ($step) {
            // Match by type first
            if ($interaction->type !== $step['type']) {
                return false;
            }

            // Additional matching logic based on step ID
            switch ($step['id']) {
                case 'form_submission':
                    return $interaction->type === 'form_submission';
                    
                case 'welcome_email':
                    return $interaction->type === 'email' && 
                           isset($interaction->metadata['campaign']) &&
                           str_contains($interaction->metadata['campaign'], 'welcome');
                           
                case 'enterprise_welcome':
                    return $interaction->type === 'email' && 
                           isset($interaction->metadata['template']) &&
                           str_contains($interaction->metadata['template'], 'enterprise');
                           
                case 'discovery_task':
                    return $interaction->type === 'task' && 
                           isset($interaction->metadata['task_title']) &&
                           str_contains(strtolower($interaction->metadata['task_title']), 'discovery');
                           
                default:
                    return $interaction->type === $step['type'];
            }
        });
    }

    /**
     * Get current phase based on progress
     */
    private function getCurrentPhase($journeyPhases)
    {
        foreach ($journeyPhases as $phaseName => $phase) {
            if ($phase['status'] === 'in_progress') {
                return $phaseName;
            }
        }
        
        // If no in-progress phase, return first pending phase
        foreach ($journeyPhases as $phaseName => $phase) {
            if ($phase['status'] === 'pending') {
                return $phaseName;
            }
        }
        
        return 'Customer Success'; // All phases completed
    }

    /**
     * Get next step to be completed
     */
    private function getNextStep($timelineSteps)
    {
        $pendingStep = collect($timelineSteps)
            ->where('status', 'pending')
            ->sortBy('order')
            ->first();
            
        return $pendingStep ? $pendingStep['title'] : 'Journey Complete';
    }

    /**
     * Get journey events for a contact
     */
    public function getJourneyEvents($contactId)
    {
        try {
            $contact = Contact::findOrFail($contactId);
            
            $events = $contact->interactions()
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($interaction) {
                    return [
                        'id' => $interaction->id,
                        'type' => $interaction->type,
                        'title' => $this->getEventTitle($interaction),
                        'message' => $interaction->message,
                        'details' => $interaction->details,
                        'metadata' => $interaction->metadata,
                        'created_at' => $interaction->created_at->toIso8601String(),
                        'updated_at' => $interaction->updated_at->toIso8601String(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'contact_id' => $contactId,
                    'events' => $events,
                    'total_events' => $events->count(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching journey events: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching journey events',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Add new journey event
     */
    public function addJourneyEvent(Request $request, $contactId)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|string|max:50',
            'title' => 'nullable|string|max:255',
            'message' => 'nullable|string|max:255',
            'details' => 'nullable|string',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $contact = Contact::findOrFail($contactId);
            
            $event = $contact->interactions()->create([
                'type' => $request->type,
                'message' => $request->message ?? $request->title ?? "New {$request->type} event",
                'details' => $request->details,
                'metadata' => array_merge($request->metadata ?? [], [
                    'title' => $request->title,
                    'added_via_api' => true,
                    'added_at' => now()->toIso8601String()
                ]),
            ]);

            // Update contact's last activity
            $contact->update(['last_activity' => now()]);

            return response()->json([
                'success' => true,
                'data' => [
                    'event' => [
                        'id' => $event->id,
                        'type' => $event->type,
                        'title' => $this->getEventTitle($event),
                        'message' => $event->message,
                        'details' => $event->details,
                        'metadata' => $event->metadata,
                        'created_at' => $event->created_at->toIso8601String(),
                    ]
                ],
                'message' => 'Journey event added successfully'
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error adding journey event: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error adding journey event',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update journey event
     */
    public function updateJourneyEvent(Request $request, $contactId, $eventId)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|string|max:50',
            'title' => 'nullable|string|max:255',
            'message' => 'nullable|string|max:255',
            'details' => 'nullable|string',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $contact = Contact::findOrFail($contactId);
            $event = $contact->interactions()->findOrFail($eventId);
            
            $updateData = array_filter([
                'type' => $request->type,
                'message' => $request->message,
                'details' => $request->details,
            ]);

            if ($request->has('metadata')) {
                $updateData['metadata'] = array_merge($event->metadata ?? [], $request->metadata, [
                    'title' => $request->title ?? $event->metadata['title'] ?? null,
                    'updated_via_api' => true,
                    'updated_at' => now()->toIso8601String()
                ]);
            }

            $event->update($updateData);
            $contact->update(['last_activity' => now()]);

            return response()->json([
                'success' => true,
                'data' => [
                    'event' => [
                        'id' => $event->id,
                        'type' => $event->type,
                        'title' => $this->getEventTitle($event),
                        'message' => $event->message,
                        'details' => $event->details,
                        'metadata' => $event->metadata,
                        'updated_at' => $event->updated_at->toIso8601String(),
                    ]
                ],
                'message' => 'Journey event updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating journey event: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating journey event',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Delete journey event
     */
    public function deleteJourneyEvent($contactId, $eventId)
    {
        try {
            $contact = Contact::findOrFail($contactId);
            $event = $contact->interactions()->findOrFail($eventId);
            
            $eventData = [
                'id' => $event->id,
                'type' => $event->type,
                'message' => $event->message,
            ];

            $event->delete();
            $contact->update(['last_activity' => now()]);

            return response()->json([
                'success' => true,
                'data' => $eventData,
                'message' => 'Journey event deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting journey event: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting journey event',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get journey stages for a contact
     */
    public function getJourneyStages($contactId)
    {
        try {
            $contact = Contact::with(['journeyExecutions.journey.steps'])->findOrFail($contactId);
            
            $stages = [];
            
            foreach ($contact->journeyExecutions as $execution) {
                $journey = $execution->journey;
                $steps = $journey->steps()->orderBy('order_no')->get();
                $executionData = $execution->execution_data ?? [];
                $completedSteps = $executionData['completed_steps'] ?? [];

                foreach ($steps as $step) {
                    $isCompleted = in_array($step->id, $completedSteps);
                    $isCurrent = $step->id == $execution->current_step_id;
                    
                    $stages[] = [
                        'id' => $step->id,
                        'journey_id' => $journey->id,
                        'journey_name' => $journey->name,
                        'stage_name' => $step->getDescription(),
                        'stage_type' => $step->step_type,
                        'order' => $step->order_no,
                        'status' => $isCompleted ? 'completed' : ($isCurrent ? 'active' : 'pending'),
                        'completed_at' => $isCompleted ? ($executionData['step_completed_' . $step->id] ?? null) : null,
                        'config' => $step->config,
                        'conditions' => $step->conditions,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'contact_id' => $contactId,
                    'stages' => $stages,
                    'total_stages' => count($stages),
                    'completed_stages' => collect($stages)->where('status', 'completed')->count(),
                    'active_stages' => collect($stages)->where('status', 'active')->count(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching journey stages: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching journey stages',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Mark journey stage as complete
     */
    public function completeJourneyStage($contactId, $stageId)
    {
        try {
            $contact = Contact::findOrFail($contactId);
            $execution = JourneyExecution::where('contact_id', $contactId)
                ->where('current_step_id', $stageId)
                ->first();

            if (!$execution) {
                return response()->json([
                    'success' => false,
                    'message' => 'Journey stage not found or not currently active'
                ], 404);
            }

            $executionData = $execution->execution_data ?? [];
            $completedSteps = $executionData['completed_steps'] ?? [];
            
            // Mark current step as completed
            if (!in_array($stageId, $completedSteps)) {
                $completedSteps[] = $stageId;
                $executionData['completed_steps'] = $completedSteps;
                $executionData['step_completed_' . $stageId] = now()->toIso8601String();
            }

            // Find next step
            $journey = $execution->journey;
            $nextStep = $journey->steps()
                ->where('order_no', '>', $execution->currentStep->order_no)
                ->orderBy('order_no')
                ->first();

            if ($nextStep) {
                $execution->update([
                    'current_step_id' => $nextStep->id,
                    'execution_data' => $executionData,
                    'next_step_at' => now()->addMinutes(5), // Default 5 minutes for next step
                    'updated_at' => now(),
                ]);
            } else {
                // Journey completed
                $execution->update([
                    'current_step_id' => null,
                    'status' => 'completed',
                    'execution_data' => $executionData,
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $contact->update(['last_activity' => now()]);

            return response()->json([
                'success' => true,
                'data' => [
                    'stage_id' => $stageId,
                    'completed_at' => now()->toIso8601String(),
                    'next_stage_id' => $nextStep?->id,
                    'journey_status' => $nextStep ? 'in_progress' : 'completed'
                ],
                'message' => 'Journey stage marked as complete'
            ]);

        } catch (\Exception $e) {
            Log::error('Error completing journey stage: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error completing journey stage',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get all available journey stages
     */
    public function getAllJourneyStages()
    {
        try {
            $journeys = Journey::with('steps')->where('is_active', true)->get();
            
            $allStages = [];
            foreach ($journeys as $journey) {
                foreach ($journey->steps as $step) {
                    $allStages[] = [
                        'id' => $step->id,
                        'journey_id' => $journey->id,
                        'journey_name' => $journey->name,
                        'stage_name' => $step->getDescription(),
                        'stage_type' => $step->step_type,
                        'order' => $step->order_no,
                        'config' => $step->config,
                        'conditions' => $step->conditions,
                        'is_active' => $step->is_active,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'stages' => $allStages,
                    'total_stages' => count($allStages),
                    'journeys_count' => $journeys->count(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching all journey stages: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching journey stages',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update journey stage
     */
    public function updateJourneyStage(Request $request, $stageId)
    {
        $validator = Validator::make($request->all(), [
            'stage_name' => 'nullable|string|max:255',
            'config' => 'nullable|array',
            'conditions' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $step = JourneyStep::findOrFail($stageId);
            
            $updateData = array_filter([
                'config' => $request->config,
                'conditions' => $request->conditions,
                'is_active' => $request->is_active,
            ]);

            $step->update($updateData);

            return response()->json([
                'success' => true,
                'data' => [
                    'stage' => [
                        'id' => $step->id,
                        'journey_id' => $step->journey_id,
                        'stage_name' => $step->getDescription(),
                        'stage_type' => $step->step_type,
                        'config' => $step->config,
                        'conditions' => $step->conditions,
                        'is_active' => $step->is_active,
                        'updated_at' => $step->updated_at->toIso8601String(),
                    ]
                ],
                'message' => 'Journey stage updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating journey stage: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating journey stage',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get event title from interaction
     */
    private function getEventTitle($interaction)
    {
        if (isset($interaction->metadata['title'])) {
            return $interaction->metadata['title'];
        }

        $titles = [
            'email' => 'Email Interaction',
            'call' => 'Phone Call',
            'meeting' => 'Meeting',
            'task' => 'Task Created',
            'form_submission' => 'Form Submission',
            'campaign' => 'Campaign Activity',
            'note' => 'Note Added',
            'website_visit' => 'Website Visit',
        ];

        return $titles[$interaction->type] ?? ucfirst(str_replace('_', ' ', $interaction->type));
    }

    /**
     * Get color for interaction type
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
