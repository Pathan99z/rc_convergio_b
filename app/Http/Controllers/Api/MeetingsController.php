<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\Contact;
use App\Services\MeetingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class MeetingsController extends Controller
{
    protected MeetingService $meetingService;

    public function __construct(MeetingService $meetingService)
    {
        $this->meetingService = $meetingService;
    }

    /**
     * Get all meetings for the authenticated user's tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $query = Meeting::forTenant($tenantId)
            ->with(['contact:id,first_name,last_name,email', 'user:id,name']);

        // Filter by user if provided
        if ($request->has('user_id')) {
            $query->forUser($request->get('user_id'));
        }

        // Filter by contact if provided
        if ($request->has('contact_id')) {
            $query->forContact($request->get('contact_id'));
        }

        // Filter by status if provided
        if ($request->has('status')) {
            $query->withStatus($request->get('status'));
        }

        // Filter by integration provider if provided
        if ($request->has('provider')) {
            $query->fromProvider($request->get('provider'));
        }

        // Filter by date range if provided
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->inDateRange($request->get('start_date'), $request->get('end_date'));
        }

        // Filter upcoming meetings if requested
        if ($request->boolean('upcoming')) {
            $query->upcoming();
        }

        $meetings = $query->orderBy('scheduled_at', 'desc')
            ->paginate($request->get('per_page', 15));

        // Add additional data to each meeting
        $meetings->getCollection()->transform(function ($meeting) {
            $meeting->meeting_link = $meeting->getMeetingLink();
            $meeting->meeting_id = $meeting->getMeetingId();
            $meeting->duration_formatted = $meeting->getDurationFormatted();
            $meeting->summary = $meeting->getSummary();
            $meeting->is_upcoming = $meeting->isUpcoming();
            $meeting->is_in_progress = $meeting->isInProgress();
            $meeting->is_completed = $meeting->isCompleted();
            return $meeting;
        });

        return response()->json([
            'data' => $meetings->items(),
            'meta' => [
                'current_page' => $meetings->currentPage(),
                'last_page' => $meetings->lastPage(),
                'per_page' => $meetings->perPage(),
                'total' => $meetings->total(),
            ],
            'message' => 'Meetings retrieved successfully'
        ]);
    }

    /**
     * Create a new meeting.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'contact_id' => 'required|integer|exists:contacts,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'scheduled_at' => 'required|date|after:now',
            'duration_minutes' => 'nullable|integer|min:15|max:480',
            'location' => 'nullable|string|max:255',
            'status' => [
                'nullable',
                'string',
                Rule::in(array_keys(Meeting::getAvailableStatuses()))
            ],
            'integration_provider' => [
                'nullable',
                'string',
                Rule::in(array_keys(Meeting::getAvailableProviders()))
            ],
            'integration_data' => 'nullable|array',
            'attendees' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        // Verify contact belongs to tenant
        $contact = Contact::forTenant($tenantId)->findOrFail($validated['contact_id']);

        // Use authenticated user if user_id not provided
        $validated['user_id'] = $validated['user_id'] ?? $user->id;

        try {
            $meeting = $this->meetingService->createMeeting($validated, $tenantId);

            return response()->json([
                'data' => [
                    'id' => $meeting->id,
                    'title' => $meeting->title,
                    'contact_id' => $meeting->contact_id,
                    'user_id' => $meeting->user_id,
                    'scheduled_at' => $meeting->scheduled_at->toISOString(),
                    'duration_minutes' => $meeting->duration_minutes,
                    'location' => $meeting->location,
                    'status' => $meeting->status,
                    'provider' => $meeting->integration_provider,
                    'link' => $meeting->getMeetingLink(),
                    'summary' => $meeting->getSummary(),
                ],
                'message' => 'Meeting created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create meeting',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync meetings from Google Calendar.
     */
    public function syncGoogle(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'meetings' => 'required|array',
            'meetings.*.id' => 'required|string',
            'meetings.*.title' => 'required|string',
            'meetings.*.start_time' => 'required|date',
            'meetings.*.duration_minutes' => 'required|integer',
            'meetings.*.attendees' => 'nullable|array',
        ]);

        try {
            $result = $this->meetingService->syncFromGoogle($user->id, $tenantId, $validated['meetings']);

            return response()->json([
                'data' => $result,
                'message' => 'Google meetings synced successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to sync Google meetings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync meetings from Outlook Calendar.
     */
    public function syncOutlook(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'meetings' => 'required|array',
            'meetings.*.id' => 'required|string',
            'meetings.*.subject' => 'required|string',
            'meetings.*.start_time' => 'required|date',
            'meetings.*.duration_minutes' => 'required|integer',
            'meetings.*.attendees' => 'nullable|array',
        ]);

        try {
            $result = $this->meetingService->syncFromOutlook($user->id, $tenantId, $validated['meetings']);

            return response()->json([
                'data' => $result,
                'message' => 'Outlook meetings synced successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to sync Outlook meetings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available meeting statuses.
     */
    public function getStatuses(): JsonResponse
    {
        return response()->json([
            'data' => Meeting::getAvailableStatuses(),
            'message' => 'Meeting statuses retrieved successfully'
        ]);
    }

    /**
     * Get available integration providers.
     */
    public function getProviders(): JsonResponse
    {
        return response()->json([
            'data' => Meeting::getAvailableProviders(),
            'message' => 'Integration providers retrieved successfully'
        ]);
    }
}
