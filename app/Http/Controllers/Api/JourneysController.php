<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Journey;
use App\Models\JourneyStep;
use App\Models\JourneyExecution;
use App\Models\Contact;
use App\Services\JourneyEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class JourneysController extends Controller
{
    protected JourneyEngine $journeyEngine;

    public function __construct(JourneyEngine $journeyEngine)
    {
        $this->journeyEngine = $journeyEngine;
    }

    /**
     * Get all journeys for the authenticated user's tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $query = Journey::where('tenant_id', $tenantId)
            ->with(['steps' => function ($query) {
                $query->orderBy('order_no');
            }]);

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Filter by active status if provided
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $journeys = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        // Add step descriptions to each journey
        $journeys->getCollection()->transform(function ($journey) {
            $journey->steps->transform(function ($step) {
                $step->description = $step->getDescription();
                return $step;
            });
            $journey->stats = $journey->getStats();
            return $journey;
        });

        return response()->json([
            'data' => $journeys->items(),
            'meta' => [
                'current_page' => $journeys->currentPage(),
                'last_page' => $journeys->lastPage(),
                'per_page' => $journeys->perPage(),
                'total' => $journeys->total(),
            ],
            'message' => 'Journeys retrieved successfully'
        ]);
    }

    /**
     * Create a new journey.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => [
                'nullable',
                'string',
                Rule::in(array_keys(Journey::getAvailableStatuses()))
            ],
            'settings' => 'nullable|array',
            'steps' => 'required|array|min:1',
            'steps.*.step_type' => [
                'required',
                'string',
                Rule::in(array_keys(JourneyStep::getAvailableStepTypes()))
            ],
            'steps.*.config' => 'required|array',
            'steps.*.order_no' => 'required|integer|min:1',
            'steps.*.conditions' => 'nullable|array',
        ]);

        try {
            DB::beginTransaction();

            $journey = Journey::create([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'status' => $validated['status'] ?? 'draft',
                'settings' => $validated['settings'] ?? [],
                'tenant_id' => $tenantId,
                'created_by' => $user->id,
            ]);

            // Create journey steps
            foreach ($validated['steps'] as $stepData) {
                $step = JourneyStep::create([
                    'journey_id' => $journey->id,
                    'step_type' => $stepData['step_type'],
                    'config' => $stepData['config'],
                    'order_no' => $stepData['order_no'],
                    'conditions' => $stepData['conditions'] ?? null,
                ]);

                // Validate step configuration
                if (!$step->validateConfig()) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Invalid step configuration',
                        'errors' => ['steps' => ['Step configuration is invalid']]
                    ], 422);
                }
            }

            DB::commit();

            $journey->load(['steps' => function ($query) {
                $query->orderBy('order_no');
            }]);

            // Add step descriptions
            $journey->steps->transform(function ($step) {
                $step->description = $step->getDescription();
                return $step;
            });

            return response()->json([
                'data' => $journey,
                'message' => 'Journey created successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create journey',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific journey with steps.
     */
    public function show(Request $request, $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $journey = Journey::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->with(['steps' => function ($query) {
                $query->orderBy('order_no');
            }])
            ->firstOrFail();

        // Add step descriptions
        $journey->steps->transform(function ($step) {
            $step->description = $step->getDescription();
            return $step;
        });

        $journey->stats = $journey->getStats();

        return response()->json([
            'data' => $journey,
            'message' => 'Journey retrieved successfully'
        ]);
    }

    /**
     * Update a journey.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $journey = Journey::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => [
                'sometimes',
                'string',
                Rule::in(array_keys(Journey::getAvailableStatuses()))
            ],
            'settings' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $journey->update($validated);

            $journey->load(['steps' => function ($query) {
                $query->orderBy('order_no');
            }]);

            // Add step descriptions
            $journey->steps->transform(function ($step) {
                $step->description = $step->getDescription();
                return $step;
            });

            return response()->json([
                'data' => $journey,
                'message' => 'Journey updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update journey',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a journey.
     */
    public function destroy($id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $journey = Journey::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        try {
            $journey->delete();

            return response()->json([
                'message' => 'Journey deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete journey',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Run a journey for a specific contact.
     */
    public function runForContact(Request $request, $journeyId, $contactId): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        // Verify journey exists and belongs to tenant
        $journey = Journey::where('id', $journeyId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        // Verify contact exists and belongs to tenant
        $contact = Contact::where('id', $contactId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        try {
            $execution = $this->journeyEngine->startJourney($journey, $contact);

            return response()->json([
                'data' => [
                    'journey_id' => $journey->id,
                    'name' => $journey->name,
                    'contact_id' => $contact->id,
                    'execution_id' => $execution->id,
                    'status' => $execution->status,
                    'started_at' => $execution->started_at,
                    'next_step_at' => $execution->next_step_at,
                ],
                'message' => 'Journey started successfully for contact'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to start journey for contact',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get journey executions.
     */
    public function getExecutions(Request $request, $journeyId): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        // Verify journey exists and belongs to tenant
        $journey = Journey::where('id', $journeyId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $query = JourneyExecution::where('journey_id', $journeyId)
            ->where('tenant_id', $tenantId)
            ->with(['contact:id,first_name,last_name,email', 'currentStep:id,step_type,order_no']);

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        $executions = $query->orderBy('started_at', 'desc')
            ->paginate($request->get('per_page', 15));

        // Add execution details
        $executions->getCollection()->transform(function ($execution) {
            $execution->progress_percentage = $execution->getProgressPercentage();
            $execution->duration_minutes = $execution->getDuration();
            return $execution;
        });

        return response()->json([
            'data' => $executions->items(),
            'meta' => [
                'current_page' => $executions->currentPage(),
                'last_page' => $executions->lastPage(),
                'per_page' => $executions->perPage(),
                'total' => $executions->total(),
            ],
            'message' => 'Journey executions retrieved successfully'
        ]);
    }

    /**
     * Get available journey statuses.
     */
    public function getStatuses(): JsonResponse
    {
        return response()->json([
            'data' => Journey::getAvailableStatuses(),
            'message' => 'Journey statuses retrieved successfully'
        ]);
    }

    /**
     * Get available step types.
     */
    public function getStepTypes(): JsonResponse
    {
        return response()->json([
            'data' => JourneyStep::getAvailableStepTypes(),
            'message' => 'Step types retrieved successfully'
        ]);
    }

    /**
     * Get step type configuration schema.
     */
    public function getStepTypeSchema(Request $request): JsonResponse
    {
        $stepType = $request->get('step_type');
        
        if (!$stepType) {
            return response()->json([
                'message' => 'Step type is required'
            ], 400);
        }

        $schema = JourneyStep::getStepTypeConfigSchema($stepType);

        return response()->json([
            'data' => [
                'step_type' => $stepType,
                'schema' => $schema
            ],
            'message' => 'Step type schema retrieved successfully'
        ]);
    }

    /**
     * Get customer journey timeline by email.
     * This endpoint fetches data from existing APIs and shows the journey flow.
     */
    public function customer(Request $request): JsonResponse
    {
        try {
            $email = $request->query('email');
            
            if (!$email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email parameter is required'
                ], 400);
            }

            // Find contact by email
            $contact = Contact::where('email', $email)->first();
            
            if (!$contact) {
                return response()->json([
                    'success' => false,
                    'message' => 'No such contact exists',
                    'data' => null
                ], 404);
            }

            // Load all related data
            $contact->load([
                'company',
                'interactions',
                'deals.stage',
                'deals.pipeline',
                'meetings',
                'tasks',
                'activities',
                'formSubmissions.form',
                'campaignRecipients.campaign',
                'eventAttendees.event',
                'journeyExecutions.journey'
            ]);

            // Build timeline events
            $timelineEvents = $this->buildCustomerTimelineEvents($contact);
            
            // Sort timeline by date (most recent first)
            $timelineEvents = collect($timelineEvents)->sortByDesc('date')->values()->toArray();

            // Calculate journey metrics
            $metrics = $this->calculateCustomerJourneyMetrics($contact, $timelineEvents);

            return response()->json([
                'success' => true,
                'data' => [
                    'contact' => [
                        'id' => $contact->id,
                        'name' => $contact->first_name . ' ' . $contact->last_name,
                        'email' => $contact->email,
                        'phone' => $contact->phone,
                        'company' => $contact->company ? $contact->company->name : null,
                        'joined_date' => $contact->created_at->format('M d, Y'),
                        'lifecycle_stage' => $contact->lifecycle_stage,
                        'lead_score' => $contact->lead_score,
                        'source' => $contact->source,
                    ],
                    'timeline' => $timelineEvents,
                    'metrics' => $metrics,
                    'summary' => [
                        'total_events' => count($timelineEvents),
                        'last_activity' => $contact->last_activity?->format('M d, Y, g:i A'),
                        'engagement_score' => $this->calculateEngagementScore($contact, $timelineEvents),
                        'journey_stage' => $this->determineJourneyStage($contact, $timelineEvents)
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error fetching customer journey: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching customer journey',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Build timeline events for customer journey
     */
    private function buildCustomerTimelineEvents($contact)
    {
        $events = [];

        // Contact creation event
        $events[] = [
            'id' => 'contact_created',
            'type' => 'contact_created',
            'title' => 'Contact Created',
            'description' => 'Contact created via website form submission',
            'date' => $contact->created_at,
            'time' => $contact->created_at->format('g:i A'),
            'icon' => 'user-plus',
            'color' => 'blue',
            'status' => 'success',
            'metadata' => [
                'source' => $contact->source,
                'lifecycle_stage' => $contact->lifecycle_stage
            ]
        ];

        // Company association
        if ($contact->company) {
            $events[] = [
                'id' => 'company_created',
                'type' => 'company_created',
                'title' => 'Company Created',
                'description' => 'Company profile created for contact',
                'date' => $contact->company->created_at,
                'time' => $contact->company->created_at->format('g:i A'),
                'icon' => 'building',
                'color' => 'purple',
                'status' => 'success',
                'metadata' => [
                    'company_name' => $contact->company->name,
                    'industry' => $contact->company->industry
                ]
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
                'time' => $submission->created_at->format('g:i A'),
                'icon' => 'file-text',
                'color' => 'orange',
                'status' => 'success',
                'metadata' => [
                    'form_name' => $submission->form->name,
                    'status' => $submission->status
                ]
            ];
        }

        // Email interactions (campaigns)
        foreach ($contact->campaignRecipients as $recipient) {
            $events[] = [
                'id' => "email_sent_{$recipient->id}",
                'type' => 'email_sent',
                'title' => 'Email Sent',
                'description' => $this->getEmailDescription($recipient),
                'date' => $recipient->sent_at ?: $recipient->created_at,
                'time' => ($recipient->sent_at ?: $recipient->created_at)->format('g:i A'),
                'icon' => 'mail',
                'color' => 'green',
                'status' => 'success',
                'metadata' => [
                    'campaign_name' => $recipient->campaign->name,
                    'subject' => $recipient->campaign->subject,
                    'status' => $recipient->status
                ]
            ];

            // Email opened
            if ($recipient->opened_at) {
                $events[] = [
                    'id' => "email_opened_{$recipient->id}",
                    'type' => 'email_opened',
                    'title' => 'Email Opened',
                    'description' => 'Email was opened by contact',
                    'date' => $recipient->opened_at,
                    'time' => $recipient->opened_at->format('g:i A'),
                    'icon' => 'mail-open',
                    'color' => 'green',
                    'status' => 'success',
                    'metadata' => [
                        'device' => 'desktop', // You can enhance this with actual device detection
                        'campaign_name' => $recipient->campaign->name
                    ]
                ];
            }

            // Email clicked
            if ($recipient->clicked_at) {
                $events[] = [
                    'id' => "email_clicked_{$recipient->id}",
                    'type' => 'email_clicked',
                    'title' => 'Email Clicked',
                    'description' => 'Email link was clicked by contact',
                    'date' => $recipient->clicked_at,
                    'time' => $recipient->clicked_at->format('g:i A'),
                    'icon' => 'mouse-pointer',
                    'color' => 'blue',
                    'status' => 'success',
                    'metadata' => [
                        'campaign_name' => $recipient->campaign->name
                    ]
                ];
            }
        }

        // Deals
        foreach ($contact->deals as $deal) {
            $events[] = [
                'id' => "deal_proposed_{$deal->id}",
                'type' => 'deal_proposed',
                'title' => 'Deal Proposed',
                'description' => 'Sales deal proposed to contact',
                'date' => $deal->created_at,
                'time' => $deal->created_at->format('g:i A'),
                'icon' => 'clock',
                'color' => 'yellow',
                'status' => 'success',
                'metadata' => [
                    'deal_value' => $deal->value,
                    'deal_title' => $deal->title,
                    'stage' => $deal->stage?->name,
                    'pipeline' => $deal->pipeline?->name,
                    'owner' => $deal->owner?->name
                ]
            ];

            // Deal closed
            if ($deal->closed_date) {
                $events[] = [
                    'id' => "deal_closed_{$deal->id}",
                    'type' => 'deal_closed',
                    'title' => 'Deal ' . ucfirst($deal->status),
                    'description' => "Deal '{$deal->title}' was {$deal->status}",
                    'date' => $deal->closed_date,
                    'time' => $deal->closed_date->format('g:i A'),
                    'icon' => $deal->status === 'won' ? 'check-circle' : 'x-circle',
                    'color' => $deal->status === 'won' ? 'green' : 'red',
                    'status' => 'success',
                    'metadata' => [
                        'deal_value' => $deal->value,
                        'close_reason' => $deal->close_reason
                    ]
                ];
            }
        }

        // Meetings
        foreach ($contact->meetings as $meeting) {
            $events[] = [
                'id' => "meeting_scheduled_{$meeting->id}",
                'type' => 'meeting_scheduled',
                'title' => 'Meeting Scheduled',
                'description' => $meeting->title,
                'date' => $meeting->scheduled_at,
                'time' => $meeting->scheduled_at->format('g:i A'),
                'icon' => 'calendar',
                'color' => 'blue',
                'status' => 'success',
                'metadata' => [
                    'duration' => $meeting->duration_minutes,
                    'location' => $meeting->location,
                    'status' => $meeting->status
                ]
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
                'time' => $interaction->created_at->format('g:i A'),
                'icon' => $this->getInteractionIcon($interaction->type),
                'color' => $this->getInteractionColor($interaction->type),
                'status' => 'success',
                'metadata' => [
                    'interaction_type' => $interaction->type,
                    'details' => $interaction->details
                ]
            ];
        }

        return $events;
    }

    /**
     * Get email description based on campaign type
     */
    private function getEmailDescription($recipient)
    {
        $campaign = $recipient->campaign;
        
        if (str_contains(strtolower($campaign->name), 'welcome')) {
            return 'Welcome email sent to new contact';
        }
        
        if (str_contains(strtolower($campaign->name), 'follow')) {
            return 'Follow-up email with product information sent';
        }
        
        return "Email sent: {$campaign->name}";
    }

    /**
     * Calculate customer journey metrics
     */
    private function calculateCustomerJourneyMetrics($contact, $timelineEvents)
    {
        $emailEvents = collect($timelineEvents)->where('type', 'email_sent');
        $openedEvents = collect($timelineEvents)->where('type', 'email_opened');
        $clickedEvents = collect($timelineEvents)->where('type', 'email_clicked');
        $dealEvents = collect($timelineEvents)->where('type', 'deal_proposed');
        $meetingEvents = collect($timelineEvents)->where('type', 'meeting_scheduled');

        return [
            'total_emails_sent' => $emailEvents->count(),
            'emails_opened' => $openedEvents->count(),
            'emails_clicked' => $clickedEvents->count(),
            'open_rate' => $emailEvents->count() > 0 ? round(($openedEvents->count() / $emailEvents->count()) * 100, 1) : 0,
            'click_rate' => $emailEvents->count() > 0 ? round(($clickedEvents->count() / $emailEvents->count()) * 100, 1) : 0,
            'deals_proposed' => $dealEvents->count(),
            'meetings_scheduled' => $meetingEvents->count(),
            'total_interactions' => collect($timelineEvents)->count(),
            'last_activity' => $contact->last_activity?->format('M d, Y, g:i A')
        ];
    }

    /**
     * Calculate engagement score
     */
    private function calculateEngagementScore($contact, $timelineEvents)
    {
        $score = 0;
        $totalEvents = count($timelineEvents);
        
        if ($totalEvents > 0) {
            $score += min(40, $totalEvents * 5); // Base score from activity
        }
        
        if ($contact->lead_score > 0) {
            $score += min(30, $contact->lead_score);
        }
        
        if ($contact->company) {
            $score += 10;
        }
        
        if (collect($timelineEvents)->contains('type', 'email_opened')) {
            $score += 10;
        }
        
        if (collect($timelineEvents)->contains('type', 'deal_proposed')) {
            $score += 10;
        }
        
        return min(100, $score);
    }

    /**
     * Determine journey stage
     */
    private function determineJourneyStage($contact, $timelineEvents)
    {
        if (collect($timelineEvents)->contains('type', 'deal_closed')) {
            return 'Customer';
        }
        
        if (collect($timelineEvents)->contains('type', 'deal_proposed')) {
            return 'Opportunity';
        }
        
        if (collect($timelineEvents)->contains('type', 'meeting_scheduled')) {
            return 'Qualified Lead';
        }
        
        if (collect($timelineEvents)->contains('type', 'email_opened')) {
            return 'Engaged Lead';
        }
        
        if (collect($timelineEvents)->contains('type', 'form_submission')) {
            return 'New Lead';
        }
        
        return 'Prospect';
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
