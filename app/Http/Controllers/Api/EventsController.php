<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EventsController extends Controller
{
    /**
     * Get all events for the authenticated user's tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $query = Event::where('tenant_id', $tenantId)
            ->with(['attendees' => function ($query) {
                $query->with('contact:id,name,email');
            }]);

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

        $events = $query->orderBy('scheduled_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $events->items(),
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
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => [
                'required',
                'string',
                Rule::in(array_keys(Event::getAvailableTypes()))
            ],
            'scheduled_at' => 'required|date|after:now',
            'location' => 'nullable|string|max:255',
            'settings' => 'nullable|array',
        ]);

        try {
            DB::beginTransaction();

            $event = Event::create([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'type' => $validated['type'],
                'scheduled_at' => $validated['scheduled_at'],
                'location' => $validated['location'],
                'settings' => $validated['settings'] ?? [],
                'tenant_id' => $tenantId,
                'created_by' => $user->id,
            ]);

            DB::commit();

            return response()->json([
                'data' => $event->load(['attendees.contact:id,name,email']),
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

        $event = Event::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->with(['attendees.contact:id,name,email'])
            ->firstOrFail();

        // Add RSVP statistics
        $event->rsvp_stats = $event->getRsvpStats();

        return response()->json([
            'data' => $event,
            'message' => 'Event retrieved successfully'
        ]);
    }

    /**
     * Update an event.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $event = Event::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'type' => [
                'sometimes',
                'string',
                Rule::in(array_keys(Event::getAvailableTypes()))
            ],
            'scheduled_at' => 'sometimes|date',
            'location' => 'nullable|string|max:255',
            'settings' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $event->update($validated);

            return response()->json([
                'data' => $event->load(['attendees.contact:id,name,email']),
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

        try {
            $event->delete();

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
    public function addAttendee(Request $request, $eventId): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        // Verify event exists and belongs to tenant
        $event = Event::where('id', $eventId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $validated = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'rsvp_status' => [
                'required',
                'string',
                Rule::in(array_keys(EventAttendee::getAvailableRsvpStatuses()))
            ],
            'metadata' => 'nullable|array',
        ]);

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
                // Create new attendee
                $attendee = EventAttendee::create([
                    'event_id' => $eventId,
                    'contact_id' => $validated['contact_id'],
                    'rsvp_status' => $validated['rsvp_status'],
                    'rsvp_at' => now(),
                    'metadata' => $validated['metadata'] ?? [],
                    'tenant_id' => $tenantId,
                ]);
            }

            DB::commit();

            return response()->json([
                'data' => $attendee->load('contact:id,name,email'),
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

        $query = EventAttendee::where('event_id', $eventId)
            ->where('tenant_id', $tenantId)
            ->with('contact:id,name,email');

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

        $attendee = EventAttendee::where('id', $attendeeId)
            ->where('event_id', $eventId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        try {
            $attendee->markAsAttended();

            return response()->json([
                'data' => $attendee->load('contact:id,name,email'),
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
}
