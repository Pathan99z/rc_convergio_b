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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
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
        $userId = $user->id;

        // Create cache key with tenant and user isolation
        $cacheKey = "meetings_list_{$tenantId}_{$userId}_" . md5(serialize($request->all()));
        
        // Cache meetings list for 5 minutes (300 seconds)
        $meetings = Cache::remember($cacheKey, 300, function () use ($tenantId, $request) {
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
            
            return $meetings;
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

            // Clear cache after creating meeting
            $this->clearMeetingsCache($tenantId, $user->id);

            // Check if integration requires authentication
            $integrationData = $meeting->integration_data ?? [];
            $authRequired = $integrationData['auth_required'] ?? false;
            
            $response = [
                'data' => new MeetingResource($meeting->load(['contact', 'user'])),
                'message' => 'Meeting created successfully'
            ];
            
            // Add auth info if required
            if ($authRequired) {
                $provider = $meeting->integration_provider ?? 'outlook';
                $response['auth_required'] = true;
                $response['auth_url'] = url("/api/meetings/oauth/{$provider}");
                $response['message'] = $integrationData['message'] ?? 'Authentication required for meeting integration';
            }

            return response()->json($response, 201);

        } catch (\Exception $e) {
            // Check if it's an authentication error for Google Meet
            if (str_contains($e->getMessage(), 'authenticate with Google')) {
                return response()->json([
                    'message' => 'Google Meet authentication required',
                    'error' => $e->getMessage(),
                    'auth_required' => true,
                    'auth_url' => url('/api/meetings/oauth/google')
                ], 400);
            }
            
            // Check if it's an authentication error for Outlook
            if (str_contains($e->getMessage(), 'Outlook') || str_contains($e->getMessage(), 'outlook')) {
                return response()->json([
                    'message' => 'Outlook authentication required',
                    'error' => $e->getMessage(),
                    'auth_required' => true,
                    'auth_url' => url('/api/meetings/oauth/outlook')
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
            'meetings' => 'present|array', // 'present' allows empty arrays, unlike 'required'
            'meetings.*.id' => 'required|string',
            'meetings.*.title' => 'required|string',
            'meetings.*.start_time' => 'required|date',
            'meetings.*.duration_minutes' => 'required|integer',
            'meetings.*.attendees' => 'nullable|array',
        ]);

        // Handle empty meetings array gracefully
        if (empty($validated['meetings'])) {
            return response()->json([
                'data' => [
                    'synced' => [],
                    'errors' => []
                ],
                'message' => 'No Google meetings to sync'
            ]);
        }

        try {
            $result = $this->meetingService->syncFromGoogle($user->id, $tenantId, $validated['meetings']);

            // Clear cache after syncing Google meetings
            $this->clearMeetingsCache($tenantId, $user->id);

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
        $tenantId = $user->tenant_id ?? $user->id;

        // Check if meetings array is provided (for manual sync)
        if ($request->has('meetings')) {
            $meetings = $request->input('meetings', []);
            
            // Handle empty meetings array gracefully - trigger auto-fetch
            if (empty($meetings)) {
                // Fall through to auto-fetch logic below
            } else {
                // Transform Microsoft Graph API format to expected format
                $formattedMeetings = [];
                foreach ($meetings as $event) {
                    // Check if it's Microsoft Graph API format
                    if (isset($event['start']['dateTime'])) {
                        // Microsoft Graph API format - transform it
                        $startTime = \Carbon\Carbon::parse($event['start']['dateTime']);
                        $endTime = isset($event['end']['dateTime']) 
                            ? \Carbon\Carbon::parse($event['end']['dateTime'])
                            : $startTime->copy()->addHours(1);
                        $durationMinutes = $startTime->diffInMinutes($endTime);
                        
                        $formattedMeetings[] = [
                            'id' => $event['id'] ?? uniqid('outlook_'),
                            'subject' => $event['subject'] ?? 'Untitled Meeting',
                            'start_time' => $startTime->toISOString(),
                            'duration_minutes' => $durationMinutes,
                            'attendees' => array_map(function($attendee) {
                                return [
                                    'emailAddress' => [
                                        'address' => $attendee['emailAddress']['address'] ?? '',
                                        'name' => $attendee['emailAddress']['name'] ?? ''
                                    ]
                                ];
                            }, $event['attendees'] ?? []),
                        ];
                    } else {
                        // Already in expected format - use as is
                        $formattedMeetings[] = $event;
                    }
                }
                
                // Validate the formatted meetings
                $validated = validator($formattedMeetings, [
                    '*.id' => 'required|string',
                    '*.subject' => 'required|string',
                    '*.start_time' => 'required|date',
                    '*.duration_minutes' => 'required|integer',
                    '*.attendees' => 'nullable|array',
                ])->validate();

                try {
                    $result = $this->meetingService->syncFromOutlook($user->id, $tenantId, $validated);

                    // Clear cache after syncing Outlook meetings
                    $this->clearMeetingsCache($tenantId, $user->id);

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
        }

        // Auto-fetch from Microsoft Graph API
        try {
            $outlookService = app(\App\Services\OutlookIntegrationService::class);
            
            if (!$outlookService->isEnabled() || !$outlookService->isConfigured()) {
                return response()->json([
                    'message' => 'Outlook integration not configured',
                    'auth_required' => false,
                    'configured' => false
                ], 400);
            }
            
            // Get valid token
            $token = \App\Models\OutlookOAuthToken::getValidTokenForUser($user->id, $tenantId);
            
            if (!$token) {
                // Try to refresh expired token
                $storedToken = \App\Models\OutlookOAuthToken::where('user_id', $user->id)
                    ->where('tenant_id', $tenantId)
                    ->first();
                    
                if ($storedToken && !empty($storedToken->refresh_token)) {
                    Log::info('Attempting to refresh expired Outlook token for sync', [
                        'user_id' => $user->id,
                        'tenant_id' => $tenantId
                    ]);
                    
                    $tokenData = $outlookService->refreshAccessToken($storedToken->refresh_token);
                    if ($tokenData && isset($tokenData['access_token'])) {
                        $storedToken->update([
                            'access_token' => $tokenData['access_token'],
                            'expires_at' => now()->addSeconds($tokenData['expires_in'] ?? 3600)
                        ]);
                        $token = $storedToken->fresh();
                    }
                }
            }
            
            // If still no token, return auth_required with redirect URL
            if (!$token) {
                return response()->json([
                    'message' => 'Outlook account not connected. Please connect your Outlook account first.',
                    'auth_required' => true,
                    'auth_url' => url('/api/meetings/oauth/outlook')
                ], 401);
            }
            
            // Fetch calendar events from Microsoft Graph API
            $timeMin = $request->get('timeMin', now()->subDays(30)->toIso8601String());
            $timeMax = $request->get('timeMax', now()->addDays(90)->toIso8601String());
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token->access_token,
                'Content-Type' => 'application/json',
            ])->get('https://graph.microsoft.com/v1.0/me/calendar/events', [
                'startDateTime' => $timeMin,
                'endDateTime' => $timeMax,
                '$top' => 100,
                '$orderby' => 'start/dateTime',
                '$filter' => "isOnlineMeeting eq true or contains(subject,'meeting')"
            ]);
            
            if ($response->successful()) {
                $events = $response->json();
                $outlookMeetings = $events['value'] ?? [];
                
                // Convert to format expected by syncFromOutlook
                $formattedMeetings = [];
                foreach ($outlookMeetings as $event) {
                    $startTime = \Carbon\Carbon::parse($event['start']['dateTime']);
                    $endTime = \Carbon\Carbon::parse($event['end']['dateTime']);
                    $durationMinutes = $startTime->diffInMinutes($endTime);
                    
                    $formattedMeetings[] = [
                        'id' => $event['id'],
                        'subject' => $event['subject'] ?? 'Untitled Meeting',
                        'start_time' => $startTime->toISOString(),
                        'duration_minutes' => $durationMinutes,
                        'attendees' => array_map(function($attendee) {
                            return [
                                'emailAddress' => [
                                    'address' => $attendee['emailAddress']['address'] ?? '',
                                    'name' => $attendee['emailAddress']['name'] ?? ''
                                ]
                            ];
                        }, $event['attendees'] ?? []),
                        'body' => $event['body']['content'] ?? null,
                        'location' => $event['location']['displayName'] ?? null,
                        'link' => $event['onlineMeeting']['joinUrl'] ?? null,
                    ];
                }
                
                // Sync meetings to database
                $result = $this->meetingService->syncFromOutlook($user->id, $tenantId, $formattedMeetings);
                
                // Clear cache
                $this->clearMeetingsCache($tenantId, $user->id);
                
                return response()->json([
                    'data' => $result,
                    'message' => 'Outlook meetings synced successfully',
                    'fetched_count' => count($formattedMeetings)
                ]);
            }
            
            // Handle API errors
            $errorResponse = $response->json();
            $errorCode = $errorResponse['error']['code'] ?? null;
            $errorMessage = $errorResponse['error']['message'] ?? $response->body();
            
            // Check for account suspension or authentication errors
            if ($errorCode === 'ErrorAccountSuspend' || $errorCode === 'ErrorInvalidGrant' || $errorCode === 'InvalidAuthenticationToken') {
                Log::warning('Outlook account suspended or token invalid during sync', [
                    'error_code' => $errorCode,
                    'error_message' => $errorMessage,
                    'user_id' => $user->id
                ]);
                
                // Delete invalid token
                $token->delete();
                
                return response()->json([
                    'message' => $errorCode === 'ErrorAccountSuspend' 
                        ? 'Your Microsoft account is suspended. Please verify your account and reconnect Outlook.'
                        : 'Outlook authentication expired. Please reconnect your Outlook account.',
                    'auth_required' => true,
                    'auth_url' => url('/api/meetings/oauth/outlook'),
                    'error_code' => $errorCode
                ], 401);
            }
            
            Log::error('Failed to fetch Outlook calendar events', [
                'status' => $response->status(),
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'response' => $response->body(),
                'user_id' => $user->id
            ]);
            
            return response()->json([
                'message' => 'Failed to fetch Outlook calendar events',
                'error' => $errorMessage
            ], $response->status());
            
        } catch (\Exception $e) {
            Log::error('Failed to sync Outlook meetings from API', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id
            ]);
            
            return response()->json([
                'message' => 'Failed to sync Outlook meetings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Outlook calendar events.
     */
    public function getOutlookCalendar(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? $user->id;
        
        $timeMin = $request->get('timeMin', now()->toIso8601String());
        $maxResults = $request->get('maxResults', 100);
        
        try {
            $outlookService = app(\App\Services\OutlookIntegrationService::class);
            
            if (!$outlookService->isEnabled() || !$outlookService->isConfigured()) {
                return response()->json([
                    'message' => 'Outlook integration not configured',
                    'connected' => false
                ], 400);
            }
            
            // Get valid token
            $token = \App\Models\OutlookOAuthToken::getValidTokenForUser($user->id, $tenantId);
            
            if (!$token) {
                // Try to refresh expired token
                $storedToken = \App\Models\OutlookOAuthToken::where('user_id', $user->id)
                    ->where('tenant_id', $tenantId)
                    ->first();
                    
                if ($storedToken && !empty($storedToken->refresh_token)) {
                    Log::info('Attempting to refresh expired Outlook token for calendar fetch', [
                        'user_id' => $user->id,
                        'tenant_id' => $tenantId
                    ]);
                    
                    $tokenData = $outlookService->refreshAccessToken($storedToken->refresh_token);
                    if ($tokenData && isset($tokenData['access_token'])) {
                        $storedToken->update([
                            'access_token' => $tokenData['access_token'],
                            'expires_at' => now()->addSeconds($tokenData['expires_in'] ?? 3600)
                        ]);
                        $token = $storedToken->fresh();
                    }
                }
            }
            
            if (!$token) {
                return response()->json([
                    'message' => 'Outlook not connected. Please connect your Outlook account first.',
                    'connected' => false
                ], 401);
            }
            
            // Fetch calendar events from Microsoft Graph API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token->access_token,
                'Content-Type' => 'application/json',
            ])->get('https://graph.microsoft.com/v1.0/me/calendar/events', [
                'startDateTime' => $timeMin,
                'endDateTime' => now()->addMonths(6)->toIso8601String(),
                '$top' => $maxResults,
                '$orderby' => 'start/dateTime',
            ]);
            
            if ($response->successful()) {
                $events = $response->json();
                
                return response()->json([
                    'data' => $events['value'] ?? [],
                    'message' => 'Outlook calendar events retrieved successfully'
                ]);
            }
            
            Log::error('Failed to fetch Outlook calendar events', [
                'status' => $response->status(),
                'response' => $response->body(),
                'user_id' => $user->id
            ]);
            
            return response()->json([
                'message' => 'Failed to fetch Outlook calendar events',
                'error' => $response->body()
            ], $response->status());
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch Outlook calendar events', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id
            ]);
            
            return response()->json([
                'message' => 'Failed to fetch Outlook calendar events',
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
        $userId = $user->id;

        // Create cache key with tenant, user, and meeting ID isolation
        $cacheKey = "meeting_show_{$tenantId}_{$userId}_{$id}";
        
        // Cache meeting detail for 15 minutes (900 seconds)
        $meeting = Cache::remember($cacheKey, 900, function () use ($tenantId, $id) {
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
            
            return $meeting;
        });

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

            // Clear cache after updating meeting
            $this->clearMeetingsCache($tenantId, $user->id);
            Cache::forget("meeting_show_{$tenantId}_{$user->id}_{$id}");

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
            
            $userId = $user->id;
            $meetingId = $meeting->id;
            
            $meeting->delete();

            // Clear cache after deleting meeting
            $this->clearMeetingsCache($tenantId, $userId);
            Cache::forget("meeting_show_{$tenantId}_{$userId}_{$meetingId}");

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

            // Clear cache after updating meeting status
            $this->clearMeetingsCache($tenantId, $user->id);
            Cache::forget("meeting_show_{$tenantId}_{$user->id}_{$id}");

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
        $userId = $user->id;

        $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        // Create cache key for meeting analytics
        $cacheKey = "meetings_analytics_{$tenantId}_{$userId}_" . md5(serialize(['start_date' => $startDate, 'end_date' => $endDate]));
        
        // Cache meeting analytics for 5 minutes (300 seconds)
        $analytics = Cache::remember($cacheKey, 300, function () use ($tenantId, $startDate, $endDate) {
            $meetings = Meeting::forTenant($tenantId)
                ->whereBetween('scheduled_at', [$startDate, $endDate])
                ->get();

            return [
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
        });

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
        $userId = $user->id;
        $limit = $request->get('limit', 10);

        // Create cache key for upcoming meetings
        $cacheKey = "meetings_upcoming_{$tenantId}_{$userId}_{$limit}";
        
        // Cache upcoming meetings for 5 minutes (300 seconds) - optimized for performance
        $meetings = Cache::remember($cacheKey, 300, function () use ($user, $tenantId, $limit) {
            return $this->meetingService->getUpcomingMeetings($user->id, $tenantId, $limit);
        });

        return response()->json([
            'data' => $meetings,
            'message' => 'Upcoming meetings retrieved successfully'
        ]);
    }

    /**
     * Clear meetings cache for a specific tenant and user.
     * This method prevents code duplication and ensures consistent cache invalidation.
     *
     * @param int $tenantId
     * @param int $userId
     * @return void
     */
    private function clearMeetingsCache(int $tenantId, int $userId): void
    {
        try {
            // Clear common cache patterns for meetings list
            $commonParams = [
                '',
                md5(serialize(['status' => 'scheduled', 'per_page' => 15])),
                md5(serialize(['upcoming' => true, 'per_page' => 15])),
            ];

            foreach ($commonParams as $params) {
                Cache::forget("meetings_list_{$tenantId}_{$userId}_{$params}");
            }

            // Clear analytics and upcoming meetings cache
            Cache::forget("meetings_analytics_{$tenantId}_{$userId}_" . md5(serialize(['start_date' => now()->subDays(30)->toDateString(), 'end_date' => now()->toDateString()])));
            Cache::forget("meetings_upcoming_{$tenantId}_{$userId}_10");

            Log::info('Meetings cache cleared', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'cleared_keys' => count($commonParams) + 2
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to clear meetings cache', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
