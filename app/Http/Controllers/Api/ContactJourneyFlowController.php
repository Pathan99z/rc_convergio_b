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
use App\Services\ContactJourneyFlowService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class ContactJourneyFlowController extends Controller
{
    protected $journeyFlowService;

    public function __construct(ContactJourneyFlowService $journeyFlowService)
    {
        $this->journeyFlowService = $journeyFlowService;
    }

    /**
     * Get contact journey flow by fetching from contacts API
     * This endpoint fetches contact details and shows the journey flow
     */
    public function getContactJourneyFlow(Request $request)
    {
        try {
            $page = $request->query('page', 1);
            $perPage = $request->query('per_page', 12);
            $sort = $request->query('sort', '-created_at');
            $timestamp = $request->query('t');

            // Fetch contacts from the API endpoint
            $contactsData = $this->fetchContactsFromApi($page, $perPage, $sort, $timestamp);

            if (!$contactsData['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch contacts from API',
                    'error' => $contactsData['error'] ?? 'Unknown error'
                ], 500);
            }

            $contacts = $contactsData['data'];
            $meta = $contactsData['meta'];

            // Process each contact to get journey flow information
            $journeyFlows = [];
            foreach ($contacts as $contactData) {
                $contactId = $contactData['id'];
                
                // Get journey flow for this contact
                $journeyFlow = $this->journeyFlowService->getContactJourneyFlow($contactId);
                
                $journeyFlows[] = [
                    'contact' => $contactData,
                    'journey_status' => $journeyFlow['status'],
                    'journey_stage' => $journeyFlow['current_stage'],
                    'journey_progress' => $journeyFlow['progress_percentage'],
                    'last_activity' => $journeyFlow['last_activity'],
                    'next_action' => $journeyFlow['next_action'],
                    'timeline_events' => $journeyFlow['timeline_events'],
                    'created_status' => $this->determineContactCreationStatus($contactData),
                    'journey_summary' => $this->generateJourneySummary($journeyFlow)
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $journeyFlows,
                'meta' => $meta,
                'summary' => [
                    'total_contacts' => $meta['total'],
                    'contacts_with_journey' => count(array_filter($journeyFlows, fn($flow) => $flow['journey_status'] !== 'no_journey')),
                    'contacts_created' => count(array_filter($journeyFlows, fn($flow) => $flow['created_status'] === 'success')),
                    'contacts_pending' => count(array_filter($journeyFlows, fn($flow) => $flow['created_status'] === 'pending')),
                    'contacts_not_found' => count(array_filter($journeyFlows, fn($flow) => $flow['created_status'] === 'not_found'))
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching contact journey flow: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching contact journey flow',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get specific contact journey flow by contact ID
     */
    public function getContactJourneyFlowById(Request $request, $contactId)
    {
        try {
            // First check if contact exists in our database
            $contact = Contact::find($contactId);
            
            if (!$contact) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contact not found',
                    'created_status' => 'not_found'
                ], 404);
            }

            // Get journey flow for this contact
            $journeyFlow = $this->journeyFlowService->getContactJourneyFlow($contactId);
            
            $response = [
                'success' => true,
                'data' => [
                    'contact' => [
                        'id' => $contact->id,
                        'first_name' => $contact->first_name,
                        'last_name' => $contact->last_name,
                        'email' => $contact->email,
                        'phone' => $contact->phone,
                        'lifecycle_stage' => $contact->lifecycle_stage,
                        'lead_score' => $contact->lead_score,
                        'source' => $contact->source,
                        'created_at' => $contact->created_at->toIso8601String(),
                        'updated_at' => $contact->updated_at->toIso8601String(),
                    ],
                    'journey_status' => $journeyFlow['status'],
                    'journey_stage' => $journeyFlow['current_stage'],
                    'journey_progress' => $journeyFlow['progress_percentage'],
                    'last_activity' => $journeyFlow['last_activity'],
                    'next_action' => $journeyFlow['next_action'],
                    'timeline_events' => $journeyFlow['timeline_events'],
                    'created_status' => 'success',
                    'journey_summary' => $this->generateJourneySummary($journeyFlow)
                ]
            ];

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('Error fetching contact journey flow by ID: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching contact journey flow',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get contact journey flow by email
     */
    public function getContactJourneyFlowByEmail(Request $request, $email)
    {
        try {
            // Find contact by email
            $contact = Contact::where('email', $email)->first();
            
            if (!$contact) {
                return response()->json([
                    'success' => false,
                    'message' => 'No such contact exists',
                    'created_status' => 'not_found'
                ], 404);
            }

            // Get journey flow for this contact
            $journeyFlow = $this->journeyFlowService->getContactJourneyFlow($contact->id);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'contact' => [
                        'id' => $contact->id,
                        'first_name' => $contact->first_name,
                        'last_name' => $contact->last_name,
                        'email' => $contact->email,
                        'phone' => $contact->phone,
                        'lifecycle_stage' => $contact->lifecycle_stage,
                        'lead_score' => $contact->lead_score,
                        'source' => $contact->source,
                        'created_at' => $contact->created_at->toIso8601String(),
                        'updated_at' => $contact->updated_at->toIso8601String(),
                    ],
                    'journey_status' => $journeyFlow['status'],
                    'journey_stage' => $journeyFlow['current_stage'],
                    'journey_progress' => $journeyFlow['progress_percentage'],
                    'last_activity' => $journeyFlow['last_activity'],
                    'next_action' => $journeyFlow['next_action'],
                    'timeline_events' => $journeyFlow['timeline_events'],
                    'created_status' => 'success',
                    'journey_summary' => $this->generateJourneySummary($journeyFlow)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching contact journey flow by email: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching contact journey flow',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get journey flow summary for all contacts
     */
    public function getJourneyFlowSummary(Request $request)
    {
        try {
            $summary = $this->journeyFlowService->getJourneyFlowSummary();
            
            return response()->json([
                'success' => true,
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching journey flow summary: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching journey flow summary',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Fetch contacts from the API endpoint
     */
    private function fetchContactsFromApi($page, $perPage, $sort, $timestamp)
    {
        try {
            // Build the API URL
            $baseUrl = config('app.url') . '/api/contacts';
            $params = [
                'page' => $page,
                'per_page' => $perPage,
                'sort' => $sort,
                't' => $timestamp
            ];

            $url = $baseUrl . '?' . http_build_query($params);

            // Make the API request
            $response = Http::timeout(30)->get($url);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'data' => $data['data'] ?? [],
                    'meta' => $data['meta'] ?? []
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'API request failed with status: ' . $response->status()
                ];
            }

        } catch (\Exception $e) {
            Log::error('Error fetching contacts from API: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Determine contact creation status
     */
    private function determineContactCreationStatus($contactData)
    {
        // Check if contact has required fields
        if (empty($contactData['email']) && empty($contactData['phone'])) {
            return 'pending';
        }

        // Check if contact has recent activity
        $createdAt = Carbon::parse($contactData['created_at']);
        $updatedAt = Carbon::parse($contactData['updated_at']);
        
        if ($createdAt->diffInMinutes($updatedAt) < 5) {
            return 'success'; // Recently created
        }

        // Check if contact has interactions
        if (isset($contactData['interactions_count']) && $contactData['interactions_count'] > 0) {
            return 'success';
        }

        return 'pending';
    }

    /**
     * Generate journey summary
     */
    private function generateJourneySummary($journeyFlow)
    {
        return [
            'status' => $journeyFlow['status'],
            'stage' => $journeyFlow['current_stage'],
            'progress' => $journeyFlow['progress_percentage'],
            'total_events' => count($journeyFlow['timeline_events']),
            'last_activity' => $journeyFlow['last_activity'],
            'next_action' => $journeyFlow['next_action'],
            'is_active' => $journeyFlow['status'] === 'active',
            'is_completed' => $journeyFlow['status'] === 'completed',
            'is_pending' => $journeyFlow['status'] === 'pending'
        ];
    }
}
