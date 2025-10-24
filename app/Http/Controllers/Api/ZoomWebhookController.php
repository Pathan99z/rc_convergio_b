<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Contact;
use App\Services\ZoomIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ZoomWebhookController extends Controller
{
    public function __construct(
        private ZoomIntegrationService $zoomService
    ) {}

    /**
     * Handle Zoom webhook events.
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        // Verify webhook signature
        $payload = $request->getContent();
        $signature = $request->header('X-Zm-Signature');
        $timestamp = $request->header('X-Zm-Request-Timestamp');

        if (!$this->zoomService->verifyWebhookSignature($payload, $signature, $timestamp)) {
            Log::warning('Invalid Zoom webhook signature', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $eventType = $request->input('event');
        $payload = $request->input('payload');

        Log::info('Zoom webhook received', [
            'event_type' => $eventType,
            'payload' => $payload
        ]);

        try {
            switch ($eventType) {
                case 'meeting.started':
                    $this->handleMeetingStarted($payload);
                    break;
                case 'meeting.ended':
                    $this->handleMeetingEnded($payload);
                    break;
                case 'meeting.participant_joined':
                    $this->handleParticipantJoined($payload);
                    break;
                case 'meeting.participant_left':
                    $this->handleParticipantLeft($payload);
                    break;
                default:
                    Log::info('Unhandled Zoom webhook event', ['event_type' => $eventType]);
            }

            return response()->json(['message' => 'Webhook processed successfully']);
        } catch (\Exception $e) {
            Log::error('Error processing Zoom webhook', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Failed to process webhook'], 500);
        }
    }

    /**
     * Handle meeting started event.
     */
    private function handleMeetingStarted(array $payload): void
    {
        $meetingId = $payload['object']['id'];
        
        // Find event by Zoom meeting ID
        $event = Event::where('settings->zoom_meeting_id', $meetingId)->first();
        
        if ($event) {
            Log::info('Meeting started for event', [
                'event_id' => $event->id,
                'meeting_id' => $meetingId
            ]);
            
            // You can add logic here to update event status, send notifications, etc.
        }
    }

    /**
     * Handle meeting ended event.
     */
    private function handleMeetingEnded(array $payload): void
    {
        $meetingId = $payload['object']['id'];
        
        // Find event by Zoom meeting ID
        $event = Event::where('settings->zoom_meeting_id', $meetingId)->first();
        
        if ($event) {
            Log::info('Meeting ended for event', [
                'event_id' => $event->id,
                'meeting_id' => $meetingId
            ]);
            
            // You can add logic here to update event status, send follow-up emails, etc.
        }
    }

    /**
     * Handle participant joined event.
     */
    private function handleParticipantJoined(array $payload): void
    {
        $meetingId = $payload['object']['id'];
        $participant = $payload['object']['participant'];
        $email = $participant['email'];
        
        // Find event by Zoom meeting ID
        $event = Event::where('settings->zoom_meeting_id', $meetingId)->first();
        
        if ($event) {
            // Find contact by email
            $contact = Contact::where('email', $email)
                ->where('tenant_id', $event->tenant_id)
                ->first();
            
            if ($contact) {
                // Find attendee record
                $attendee = EventAttendee::where('event_id', $event->id)
                    ->where('contact_id', $contact->id)
                    ->where('tenant_id', $event->tenant_id)
                    ->first();
                
                if ($attendee) {
                    // Update metadata with join information
                    $metadata = $attendee->metadata ?? [];
                    $metadata['joined_at'] = now()->toISOString();
                    $metadata['zoom_join_time'] = $payload['object']['join_time'];
                    
                    $attendee->update(['metadata' => $metadata]);
                    
                    Log::info('Participant joined meeting', [
                        'event_id' => $event->id,
                        'contact_id' => $contact->id,
                        'attendee_id' => $attendee->id
                    ]);
                }
            }
        }
    }

    /**
     * Handle participant left event.
     */
    private function handleParticipantLeft(array $payload): void
    {
        $meetingId = $payload['object']['id'];
        $participant = $payload['object']['participant'];
        $email = $participant['email'];
        
        // Find event by Zoom meeting ID
        $event = Event::where('settings->zoom_meeting_id', $meetingId)->first();
        
        if ($event) {
            // Find contact by email
            $contact = Contact::where('email', $email)
                ->where('tenant_id', $event->tenant_id)
                ->first();
            
            if ($contact) {
                // Find attendee record
                $attendee = EventAttendee::where('event_id', $event->id)
                    ->where('contact_id', $contact->id)
                    ->where('tenant_id', $event->tenant_id)
                    ->first();
                
                if ($attendee) {
                    // Update metadata with leave information
                    $metadata = $attendee->metadata ?? [];
                    $metadata['left_at'] = now()->toISOString();
                    $metadata['zoom_leave_time'] = $payload['object']['leave_time'];
                    
                    // Calculate duration if we have join time
                    if (isset($metadata['zoom_join_time'])) {
                        $joinTime = \Carbon\Carbon::parse($metadata['zoom_join_time']);
                        $leaveTime = \Carbon\Carbon::parse($metadata['zoom_leave_time']);
                        $metadata['duration_minutes'] = $leaveTime->diffInMinutes($joinTime);
                    }
                    
                    $attendee->update(['metadata' => $metadata]);
                    
                    Log::info('Participant left meeting', [
                        'event_id' => $event->id,
                        'contact_id' => $contact->id,
                        'attendee_id' => $attendee->id,
                        'duration_minutes' => $metadata['duration_minutes'] ?? null
                    ]);
                }
            }
        }
    }
}












