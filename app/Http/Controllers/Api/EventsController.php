<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Events\StoreEventRequest;
use App\Http\Requests\Events\UpdateEventRequest;
use App\Http\Requests\Events\AddAttendeeRequest;
use App\Http\Resources\EventResource;
use App\Http\Resources\EventAttendeeResource;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Contact;
use App\Services\EventService;
use App\Services\ZoomIntegrationService;
use App\Services\TeamAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class EventsController extends Controller
{
    public function __construct(
        private EventService $eventService,
        private ZoomIntegrationService $zoomService,
        private TeamAccessService $teamAccessService
    ) {}
    /**
     * Get all events for the authenticated user's tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Event::class);

        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $query = Event::where('tenant_id', $tenantId)
            ->with(['attendees' => function ($query) {
                $query->with('contact:id,first_name,last_name,email');
            }]);

        // ✅ FIX: Apply team filtering if team access is enabled
        $this->teamAccessService->applyTeamFilter($query);

        // Filter by type if provided
        if ($request->has('type')) {
            $query->where('type', $request->get('type'));
        }

        // Filter by status if provided
        if ($request->has('status')) {
            switch ($request->get('status')) {
                case 'upcoming':
                    $query->upcoming();
                    break;
                case 'past':
                    $query->past();
                    break;
                case 'active':
                    $query->active();
                    break;
            }
        }

        $filters = [
            'type' => $request->get('type'),
            'status' => $request->get('status'),
            'per_page' => $request->get('per_page', 15),
        ];

        $userId = $user->id;
        
        // Create cache key with tenant and user isolation
        $cacheKey = "events_list_{$tenantId}_{$userId}_" . md5(serialize($filters));
        
        // Cache events list for 5 minutes (300 seconds)
        $events = Cache::remember($cacheKey, 300, function () use ($tenantId, $filters) {
            return $this->eventService->getEventsForTenant($tenantId, $filters);
        });

        return response()->json([
            'data' => EventResource::collection($events->items()),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
            'message' => 'Events retrieved successfully'
        ]);
    }

    /**
     * Create a new event.
     */
    public function store(StoreEventRequest $request): JsonResponse
    {
        $this->authorize('create', Event::class);

        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validated();

        try {
            DB::beginTransaction();

            // Parse scheduled_at with proper Carbon UTC conversion
            if (isset($validated['scheduled_at'])) {
                $validated['scheduled_at'] = \Carbon\Carbon::parse($validated['scheduled_at'])->utc();
            }

            // ALWAYS enforce tenant_id and created_by for security
            $eventData = array_merge($validated, [
                'tenant_id' => $tenantId,
                'created_by' => $user->id,
            ]);

            // Log the data being created for debugging
            Log::info('Creating event with data:', [
                'tenant_id' => $tenantId,
                'created_by' => $user->id,
                'name' => $validated['name'] ?? 'N/A',
                'type' => $validated['type'] ?? 'N/A',
                'scheduled_at' => $validated['scheduled_at'] ?? 'N/A',
                'scheduled_at_parsed' => $validated['scheduled_at']->toISOString() ?? 'N/A'
            ]);

            $event = $this->eventService->createEvent($validated, $tenantId, $user->id);

            // Create Zoom meeting if it's a webinar and location is not provided
            // This will work in both mock mode and real Zoom mode
            if ($validated['type'] === 'webinar' && empty($validated['location'])) {
                Log::info('Attempting to create Zoom meeting for webinar event', [
                    'event_id' => $event->id,
                    'event_name' => $event->name,
                    'scheduled_at' => $event->scheduled_at
                ]);
                
                $zoomResult = $this->zoomService->createMeeting($event);
                
                Log::info('Zoom service result', [
                    'event_id' => $event->id,
                    'zoom_result' => $zoomResult
                ]);
                
                if ($zoomResult['success']) {
                    // Update event with Zoom meeting details
                    $event->update([
                        'location' => $zoomResult['join_url'],
                        'settings' => array_merge($event->settings ?? [], [
                            'zoom_meeting_id' => $zoomResult['meeting_id'],
                            'zoom_password' => $zoomResult['password'],
                            'zoom_start_url' => $zoomResult['start_url'],
                        ])
                    ]);
                    
                    Log::info('Zoom meeting created for event', [
                        'event_id' => $event->id,
                        'meeting_id' => $zoomResult['meeting_id'],
                        'join_url' => $zoomResult['join_url']
                    ]);
                } else {
                    Log::warning('Failed to create Zoom meeting for event', [
                        'event_id' => $event->id,
                        'error' => $zoomResult['error']
                    ]);
                    
                    // Don't fail the event creation if Zoom fails
                    // The event will be created without Zoom integration
                }
            } else {
                Log::info('Skipping Zoom meeting creation', [
                    'event_id' => $event->id,
                    'type' => $validated['type'],
                    'location_provided' => !empty($validated['location'])
                ]);
            }

            DB::commit();

            // Clear cache after creating event
            $this->clearEventsCache($tenantId, $user->id);

            return response()->json([
                'data' => new EventResource($event),
                'message' => 'Event created successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific event with attendees.
     */
    public function show(Request $request, $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $userId = $user->id;
        
        // Create cache key with tenant, user, and event ID isolation
        $cacheKey = "event_show_{$tenantId}_{$userId}_{$id}";
        
        // Cache event detail for 15 minutes (900 seconds)
        $event = Cache::remember($cacheKey, 900, function () use ($tenantId, $id) {
            $event = Event::where('id', $id)
                ->where('tenant_id', $tenantId)
                ->with(['attendees.contact:id,first_name,last_name,email'])
                ->firstOrFail();
            
            // Add RSVP statistics
            $event->rsvp_stats = $event->getRsvpStats();
            
            return $event;
        });

        $this->authorize('view', $event);

        return response()->json([
            'data' => $event,
            'message' => 'Event retrieved successfully'
        ]);
    }

    /**
     * Update an event.
     */
    public function update(UpdateEventRequest $request, $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $event = Event::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $this->authorize('update', $event);

        $validated = $request->validated();

        // Parse scheduled_at with proper Carbon UTC conversion if provided
        if (isset($validated['scheduled_at'])) {
            $validated['scheduled_at'] = \Carbon\Carbon::parse($validated['scheduled_at'])->utc();
        }

        try {
            $event->update($validated);

            // Clear cache after updating event
            $this->clearEventsCache($tenantId, $user->id);
            Cache::forget("event_show_{$tenantId}_{$user->id}_{$id}");

            return response()->json([
                'data' => $event->load(['attendees.contact:id,first_name,last_name,email']),
                'message' => 'Event updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an event.
     */
    public function destroy($id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $event = Event::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $this->authorize('delete', $event);

        try {
            $userId = $user->id;
            $eventId = $event->id;
            
            $event->delete();

            // Clear cache after deleting event
            $this->clearEventsCache($tenantId, $userId);
            Cache::forget("event_show_{$tenantId}_{$userId}_{$eventId}");

            return response()->json([
                'message' => 'Event deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add an attendee to an event (RSVP).
     */
    public function addAttendee(AddAttendeeRequest $request, $eventId): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        // Verify event exists and belongs to tenant
        $event = Event::where('id', $eventId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $this->authorize('addAttendee', $event);

        $validated = $request->validated();

        // Verify contact belongs to same tenant
        $contact = Contact::where('id', $validated['contact_id'])
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        try {
            DB::beginTransaction();

            // Check if attendee already exists
            $existingAttendee = EventAttendee::where('event_id', $eventId)
                ->where('contact_id', $validated['contact_id'])
                ->first();

            if ($existingAttendee) {
                // Update existing RSVP
                $existingAttendee->updateRsvpStatus($validated['rsvp_status']);
                $attendee = $existingAttendee;
            } else {
                // Create new attendee with tenant isolation
                $attendee = EventAttendee::create([
                    'event_id' => $eventId,
                    'contact_id' => $validated['contact_id'],
                    'rsvp_status' => $validated['rsvp_status'],
                    'rsvp_at' => now(),
                    'metadata' => $validated['metadata'] ?? [],
                    'tenant_id' => $tenantId, // Always enforce tenant isolation
                ]);
            }

            DB::commit();

            // Clear cache after adding attendee
            Cache::forget("event_show_{$tenantId}_{$user->id}_{$eventId}");

            return response()->json([
                'data' => $attendee->load('contact:id,first_name,last_name,email'),
                'message' => 'Attendee added successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to add attendee',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get attendees for a specific event.
     */
    public function getAttendees(Request $request, $eventId): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        // Verify event exists and belongs to tenant
        $event = Event::where('id', $eventId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $this->authorize('viewAttendees', $event);

        $query = EventAttendee::where('event_id', $eventId)
            ->where('tenant_id', $tenantId)
            ->with('contact:id,first_name,last_name,email');

        // Filter by RSVP status if provided
        if ($request->has('rsvp_status')) {
            $query->where('rsvp_status', $request->get('rsvp_status'));
        }

        // Filter by attendance if provided
        if ($request->has('attended')) {
            $query->where('attended', $request->boolean('attended'));
        }

        $attendees = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $attendees->items(),
            'meta' => [
                'current_page' => $attendees->currentPage(),
                'last_page' => $attendees->lastPage(),
                'per_page' => $attendees->perPage(),
                'total' => $attendees->total(),
            ],
            'message' => 'Event attendees retrieved successfully'
        ]);
    }

    /**
     * Mark an attendee as attended.
     */
    public function markAttended(Request $request, $eventId, $attendeeId): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        // Verify event exists and belongs to tenant
        $event = Event::where('id', $eventId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $this->authorize('markAttendance', $event);

        $attendee = EventAttendee::where('id', $attendeeId)
            ->where('event_id', $eventId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        try {
            $attendee->markAsAttended();

            return response()->json([
                'data' => $attendee->load('contact:id,first_name,last_name,email'),
                'message' => 'Attendee marked as attended'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to mark attendee as attended',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available event types.
     */
    public function getEventTypes(): JsonResponse
    {
        return response()->json([
            'data' => Event::getAvailableTypes(),
            'message' => 'Event types retrieved successfully'
        ]);
    }

    /**
     * Get available RSVP statuses.
     */
    public function getRsvpStatuses(): JsonResponse
    {
        return response()->json([
            'data' => EventAttendee::getAvailableRsvpStatuses(),
            'message' => 'RSVP statuses retrieved successfully'
        ]);
    }

    /**
     * Get shareable link for an event.
     */
    public function getShareLink($id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $event = Event::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $this->authorize('view', $event);

        $publicUrl = url("/api/public/events/{$id}");
        $shortUrl = $this->eventService->generateShortUrl($publicUrl);

        return response()->json([
            'data' => [
                'event_id' => $event->id,
                'event_name' => $event->name,
                'public_url' => $publicUrl,
                'short_url' => $shortUrl,
                'qr_code_url' => url("/api/events/{$id}/qr-code"),
                'share_text' => "Join {$event->name} on " . $event->scheduled_at->format('M d, Y \a\t g:i A') . " - {$publicUrl}",
            ],
            'message' => 'Share link generated successfully'
        ]);
    }

    /**
     * Generate QR code for event sharing.
     */
    public function getQrCode($id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $event = Event::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $this->authorize('view', $event);

        // Generate frontend public URL (not API URL)
        $frontendPublicUrl = $this->eventService->generateFrontendPublicUrl($event->id);
        $qrCodeData = $this->eventService->generateQrCode($frontendPublicUrl);

        return response()->json([
            'data' => [
                'event_id' => $event->id,
                'qr_code' => $qrCodeData,
                'public_url' => $frontendPublicUrl, // Frontend URL, not API URL
            ],
            'message' => 'QR code generated successfully'
        ]);
    }

    /**
     * Get calendar event data (iCal format).
     */
    public function getCalendarEvent($id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $event = Event::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $this->authorize('view', $event);

        $calendarData = $this->eventService->generateCalendarEvent($event);

        return response()->json([
            'data' => [
                'event_id' => $event->id,
                'calendar_data' => $calendarData,
                'google_calendar_url' => $calendarData['google_url'],
                'outlook_calendar_url' => $calendarData['outlook_url'],
                'ical_download_url' => url("/api/events/{$id}/calendar/ical"),
            ],
            'message' => 'Calendar event data generated successfully'
        ]);
    }

    /**
     * Send event invitations to contacts using existing email templates.
     */
    public function sendInvitations(Request $request, $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $event = Event::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $this->authorize('view', $event);

        $request->validate([
            'contact_ids' => 'required|array',
            'contact_ids.*' => 'integer|exists:contacts,id',
            'template_id' => 'nullable|integer|exists:campaign_templates,id',
            'subject' => 'nullable|string|max:255',
            'message' => 'nullable|string|max:1000',
            'send_email' => 'boolean',
        ]);

        $contactIds = $request->input('contact_ids');
        $templateId = $request->input('template_id');
        $subject = $request->input('subject', "You're invited to: {$event->name}");
        $message = $request->input('message', '');
        $sendEmail = $request->boolean('send_email', true);

        // Verify all contacts belong to the same tenant
        $contacts = Contact::whereIn('id', $contactIds)
            ->where('tenant_id', $tenantId)
            ->get();

        if ($contacts->count() !== count($contactIds)) {
            return response()->json([
                'message' => 'Some contacts not found or do not belong to your tenant'
            ], 400);
        }

        $result = $this->eventService->sendInvitationsWithTemplate($event, $contacts, $templateId, $subject, $message, $sendEmail);

        return response()->json([
            'data' => $result,
            'message' => 'Invitations sent successfully'
        ]);
    }


    /**
     * Get events analytics and statistics overview.
     */
    public function getEventsAnalytics(): JsonResponse
    {
        $this->authorize('viewAny', Event::class);

        $user = Auth::user();
        $tenantId = $user->tenant_id;
        $userId = $user->id;

        // Create cache key for events analytics
        $cacheKey = "events_analytics_{$tenantId}_{$userId}";
        
        // Cache events analytics for 5 minutes (300 seconds)
        $analytics = Cache::remember($cacheKey, 300, function () use ($tenantId) {
            return $this->eventService->getEventsAnalytics($tenantId);
        });

        return response()->json([
            'data' => $analytics,
            'message' => 'Events analytics retrieved successfully'
        ]);
    }

    /**
     * Get event analytics and statistics.
     */
    public function getAnalytics($id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $event = Event::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->with(['attendees.contact'])
            ->firstOrFail();

        $this->authorize('view', $event);

        $userId = $user->id;
        
        // Create cache key for event analytics
        $cacheKey = "event_analytics_{$tenantId}_{$userId}_{$id}";
        
        // Cache event analytics for 5 minutes (300 seconds)
        $analytics = Cache::remember($cacheKey, 300, function () use ($event) {
            return $this->eventService->getEventAnalytics($event);
        });

        return response()->json([
            'data' => $analytics,
            'message' => 'Event analytics retrieved successfully'
        ]);
    }

    /**
     * Download iCal file for event.
     */
    public function downloadIcal($id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $event = Event::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $this->authorize('view', $event);

        $calendarData = $this->eventService->generateCalendarEvent($event);
        $icalData = $calendarData['ical_data'];

        $filename = 'event-' . $event->id . '-' . now()->format('Y-m-d') . '.ics';

        return response($icalData, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, must-revalidate',
        ]);
    }

    /**
     * Clear events cache for a specific tenant and user.
     * This method prevents code duplication and ensures consistent cache invalidation.
     *
     * @param int $tenantId
     * @param int $userId
     * @return void
     */
    private function clearEventsCache(int $tenantId, int $userId): void
    {
        try {
            // Clear common cache patterns for events list
            $commonParams = [
                '',
                md5(serialize(['status' => 'upcoming', 'per_page' => 15])),
                md5(serialize(['status' => 'active', 'per_page' => 15])),
            ];

            foreach ($commonParams as $params) {
                Cache::forget("events_list_{$tenantId}_{$userId}_{$params}");
            }

            // Clear analytics cache
            Cache::forget("events_analytics_{$tenantId}_{$userId}");

            Log::info('Events cache cleared', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'cleared_keys' => count($commonParams) + 1
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to clear events cache', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }
}

