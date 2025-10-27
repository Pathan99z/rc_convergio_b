<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LeadScoringEventDetector;
use App\Services\LeadScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LeadScoringAutoDetectionController extends Controller
{
    protected LeadScoringService $leadScoringService;
    protected LeadScoringEventDetector $eventDetector;

    public function __construct(LeadScoringService $leadScoringService)
    {
        $this->leadScoringService = $leadScoringService;
        $this->eventDetector = new LeadScoringEventDetector($leadScoringService);
    }

    /**
     * Detect email events automatically
     */
    public function detectEmailEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_type' => 'required|string|in:email_open,email_click,email_bounce,email_unsubscribe,email_reply,email_forward',
            'contact_id' => 'required|integer|exists:contacts,id',
            'email_id' => 'nullable|integer',
            'campaign_id' => 'nullable|integer',
            'subject' => 'nullable|string'
        ]);

        try {
            $this->eventDetector->detectEmailEvent(
                $validated['event_type'],
                $validated['contact_id'],
                [
                    'email_id' => $validated['email_id'] ?? null,
                    'campaign_id' => $validated['campaign_id'] ?? null,
                    'subject' => $validated['subject'] ?? null
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Email event detected and processed for lead scoring'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to detect email event', [
                'event_type' => $validated['event_type'],
                'contact_id' => $validated['contact_id'],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to detect email event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Detect website events automatically
     */
    public function detectWebsiteEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_type' => 'required|string|in:page_visit,file_download,form_submission',
            'contact_id' => 'required|integer|exists:contacts,id',
            'page' => 'nullable|string',
            'url' => 'nullable|string',
            'referrer' => 'nullable|string',
            'visit_count' => 'nullable|integer',
            'session_duration' => 'nullable|integer'
        ]);

        try {
            $this->eventDetector->detectWebsiteEvent(
                $validated['event_type'],
                $validated['contact_id'],
                [
                    'page' => $validated['page'] ?? null,
                    'url' => $validated['url'] ?? null,
                    'referrer' => $validated['referrer'] ?? null,
                    'visit_count' => $validated['visit_count'] ?? 1,
                    'session_duration' => $validated['session_duration'] ?? null
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Website event detected and processed for lead scoring'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to detect website event', [
                'event_type' => $validated['event_type'],
                'contact_id' => $validated['contact_id'],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to detect website event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Detect form submission events
     */
    public function detectFormEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id' => 'required|integer|exists:contacts,id',
            'form_id' => 'nullable|integer',
            'form_name' => 'nullable|string',
            'fields' => 'nullable|array'
        ]);

        try {
            $this->eventDetector->detectFormEvent(
                $validated['contact_id'],
                [
                    'form_id' => $validated['form_id'] ?? null,
                    'form_name' => $validated['form_name'] ?? null,
                    'fields' => $validated['fields'] ?? []
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Form event detected and processed for lead scoring'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to detect form event', [
                'contact_id' => $validated['contact_id'],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to detect form event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Detect deal events
     */
    public function detectDealEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_type' => 'required|string|in:deal_created,deal_updated,deal_closed_won,deal_closed_lost',
            'contact_id' => 'required|integer|exists:contacts,id',
            'deal_id' => 'nullable|integer',
            'deal_value' => 'nullable|numeric',
            'deal_stage' => 'nullable|string'
        ]);

        try {
            $this->eventDetector->detectDealEvent(
                $validated['event_type'],
                $validated['contact_id'],
                [
                    'deal_id' => $validated['deal_id'] ?? null,
                    'deal_value' => $validated['deal_value'] ?? null,
                    'deal_stage' => $validated['deal_stage'] ?? null
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Deal event detected and processed for lead scoring'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to detect deal event', [
                'event_type' => $validated['event_type'],
                'contact_id' => $validated['contact_id'],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to detect deal event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Detect meeting events
     */
    public function detectMeetingEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_type' => 'required|string|in:meeting_scheduled,meeting_completed,meeting_cancelled',
            'contact_id' => 'required|integer|exists:contacts,id',
            'meeting_id' => 'nullable|integer',
            'meeting_type' => 'nullable|string',
            'duration' => 'nullable|integer'
        ]);

        try {
            $this->eventDetector->detectMeetingEvent(
                $validated['event_type'],
                $validated['contact_id'],
                [
                    'meeting_id' => $validated['meeting_id'] ?? null,
                    'meeting_type' => $validated['meeting_type'] ?? null,
                    'duration' => $validated['duration'] ?? null
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Meeting event detected and processed for lead scoring'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to detect meeting event', [
                'event_type' => $validated['event_type'],
                'contact_id' => $validated['contact_id'],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to detect meeting event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get auto-detection suggestions
     */
    public function getAutoSuggestions(): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            $suggestions = $this->eventDetector->autoDetectEvents($tenantId);

            return response()->json([
                'success' => true,
                'data' => $suggestions,
                'message' => 'Auto-detection suggestions retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get auto-suggestions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test event detection
     */
    public function testDetection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_type' => 'required|string',
            'contact_id' => 'required|integer|exists:contacts,id',
            'test_data' => 'nullable|array'
        ]);

        try {
            // Test the event detection without actually processing
            $contact = \App\Models\Contact::find($validated['contact_id']);
            
            if (!$contact) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contact not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'event_type' => $validated['event_type'],
                    'contact_id' => $validated['contact_id'],
                    'tenant_id' => $contact->tenant_id,
                    'test_data' => $validated['test_data'] ?? []
                ],
                'message' => 'Event detection test successful'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to test event detection',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}


