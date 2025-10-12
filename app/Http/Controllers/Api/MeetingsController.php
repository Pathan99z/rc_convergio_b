<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Meetings\StoreMeetingRequest;
use App\Http\Requests\Meetings\UpdateMeetingRequest;
use App\Http\Requests\Meetings\UpdateMeetingStatusRequest;
use App\Http\Resources\MeetingResource;
use App\Http\Resources\MeetingCollection;
use App\Models\Meeting;
use App\Models\Contact;
use App\Services\MeetingService;
use App\Services\TeamAccessService;
use App\Jobs\SendMeetingNotificationJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class MeetingsController extends Controller
{
    protected MeetingService $meetingService;

    public function __construct(MeetingService $meetingService, private TeamAccessService $teamAccessService)
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

        // ✅ FIX: Apply team filtering if team access is enabled
        $this->teamAccessService->applyTeamFilter($query);

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
            'data' => MeetingResource::collection($meetings->items()),
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
    public function store(StoreMeetingRequest $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validated();

        // Verify contact belongs to tenant
        $contact = Contact::forTenant($tenantId)->findOrFail($validated['contact_id']);

        // Use authenticated user if user_id not provided
        $validated['user_id'] = $validated['user_id'] ?? $user->id;

        try {
            $meeting = $this->meetingService->createMeeting($validated, $tenantId);

            return response()->json([
                'data' => new MeetingResource($meeting->load(['contact', 'user'])),
                'message' => 'Meeting created successfully'
            ], 201);

        } catch (\Exception $e) {
            // Check if it's an authentication error for Google Meet
            if (str_contains($e->getMessage(), 'authenticate with Google')) {
                return response()->json([
                    'message' => 'Google Meet authentication required',
                    'error' => $e->getMessage(),
                    'auth_required' => true,
                    'auth_url' => 'http://127.0.0.1:8000/api/meetings/oauth/google'
                ], 400);
            }
            
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

    /**
     * Show a specific meeting.
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $meeting = Meeting::forTenant($tenantId)
            ->with(['contact:id,first_name,last_name,email', 'user:id,name'])
            ->findOrFail($id);

        // Add additional data
        $meeting->meeting_link = $meeting->getMeetingLink();
        $meeting->meeting_id = $meeting->getMeetingId();
        $meeting->duration_formatted = $meeting->getDurationFormatted();
        $meeting->summary = $meeting->getSummary();
        $meeting->is_upcoming = $meeting->isUpcoming();
        $meeting->is_in_progress = $meeting->isInProgress();
        $meeting->is_completed = $meeting->isCompleted();

        return response()->json([
            'data' => new MeetingResource($meeting),
            'message' => 'Meeting retrieved successfully'
        ]);
    }

    /**
     * Update a meeting.
     */
    public function update(UpdateMeetingRequest $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $meeting = Meeting::forTenant($tenantId)->findOrFail($id);

        $validated = $request->validated();

        try {
            $meeting->update($validated);

            // Create activity record
            $this->meetingService->createMeetingActivity($meeting, 'updated', $validated);

            // Dispatch notification job for meeting update
            SendMeetingNotificationJob::dispatch($meeting->id, 'updated');

            return response()->json([
                'data' => new MeetingResource($meeting->fresh(['contact', 'user'])),
                'message' => 'Meeting updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update meeting',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a meeting.
     */
    public function destroy(int $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $meeting = Meeting::forTenant($tenantId)->findOrFail($id);

        try {
            // Dispatch notification job for meeting cancellation before deletion
            SendMeetingNotificationJob::dispatch($meeting->id, 'cancelled');
            
            $meeting->delete();

            return response()->json([
                'message' => 'Meeting deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete meeting',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update meeting status.
     */
    public function updateStatus(UpdateMeetingStatusRequest $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validated();

        try {
            $meeting = $this->meetingService->updateMeetingStatus(
                $id, 
                $tenantId, 
                $validated['status'], 
                $validated['notes'] ?? null
            );

            return response()->json([
                'data' => new MeetingResource($meeting->fresh(['contact', 'user'])),
                'message' => 'Meeting status updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update meeting status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get meeting analytics.
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $meetings = Meeting::forTenant($tenantId)
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->get();

        $analytics = [
            'total_meetings' => $meetings->count(),
            'completed' => $meetings->where('status', 'completed')->count(),
            'cancelled' => $meetings->where('status', 'cancelled')->count(),
            'no_show' => $meetings->where('status', 'no_show')->count(),
            'scheduled' => $meetings->where('status', 'scheduled')->count(),
            'completion_rate' => $meetings->count() > 0 ? 
                round(($meetings->where('status', 'completed')->count() / $meetings->count()) * 100, 2) : 0,
            'average_duration' => $meetings->avg('duration_minutes'),
            'by_provider' => $meetings->groupBy('integration_provider')->map->count(),
            'by_status' => $meetings->groupBy('status')->map->count(),
        ];

        return response()->json([
            'data' => $analytics,
            'message' => 'Meeting analytics retrieved successfully'
        ]);
    }

    /**
     * Get upcoming meetings for dashboard.
     */
    public function getUpcoming(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;
        $limit = $request->get('limit', 10);

        $meetings = $this->meetingService->getUpcomingMeetings($user->id, $tenantId, $limit);

        return response()->json([
            'data' => $meetings,
            'message' => 'Upcoming meetings retrieved successfully'
        ]);
    }
}
