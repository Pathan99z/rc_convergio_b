<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MeetingService
{
    /**
     * Create a new meeting.
     */
    public function createMeeting(array $data, int $tenantId): Meeting
    {
        try {
            DB::beginTransaction();

            $meeting = Meeting::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'contact_id' => $data['contact_id'],
                'user_id' => $data['user_id'],
                'scheduled_at' => $data['scheduled_at'],
                'duration_minutes' => $data['duration_minutes'] ?? 30,
                'location' => $data['location'] ?? null,
                'status' => $data['status'] ?? 'scheduled',
                'integration_provider' => $data['integration_provider'] ?? 'manual',
                'integration_data' => $data['integration_data'] ?? null,
                'attendees' => $data['attendees'] ?? null,
                'notes' => $data['notes'] ?? null,
                'tenant_id' => $tenantId,
            ]);

            // Create activity record
            $this->createMeetingActivity($meeting, 'created');

            DB::commit();

            Log::info('Meeting created successfully', [
                'meeting_id' => $meeting->id,
                'contact_id' => $meeting->contact_id,
                'user_id' => $meeting->user_id,
                'scheduled_at' => $meeting->scheduled_at,
            ]);

            return $meeting;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create meeting', [
                'data' => $data,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Sync meetings from Google Calendar.
     */
    public function syncFromGoogle(int $userId, int $tenantId, array $googleMeetings): array
    {
        $synced = [];
        $errors = [];

        try {
            DB::beginTransaction();

            foreach ($googleMeetings as $googleMeeting) {
                try {
                    // Find or create contact
                    $contact = $this->findOrCreateContactFromGoogleMeeting($googleMeeting, $tenantId);

                    // Check if meeting already exists
                    $existingMeeting = Meeting::where('integration_provider', 'google')
                        ->where('integration_data->meeting_id', $googleMeeting['id'])
                        ->where('tenant_id', $tenantId)
                        ->first();

                    if ($existingMeeting) {
                        // Update existing meeting
                        $existingMeeting->update([
                            'title' => $googleMeeting['title'],
                            'description' => $googleMeeting['description'] ?? null,
                            'scheduled_at' => $googleMeeting['start_time'],
                            'duration_minutes' => $googleMeeting['duration_minutes'],
                            'location' => $googleMeeting['location'] ?? null,
                            'integration_data' => [
                                'meeting_id' => $googleMeeting['id'],
                                'link' => $googleMeeting['link'] ?? null,
                                'calendar_id' => $googleMeeting['calendar_id'] ?? null,
                            ],
                            'attendees' => $googleMeeting['attendees'] ?? null,
                        ]);

                        $synced[] = $existingMeeting;
                    } else {
                        // Create new meeting
                        $meeting = Meeting::create([
                            'title' => $googleMeeting['title'],
                            'description' => $googleMeeting['description'] ?? null,
                            'contact_id' => $contact->id,
                            'user_id' => $userId,
                            'scheduled_at' => $googleMeeting['start_time'],
                            'duration_minutes' => $googleMeeting['duration_minutes'],
                            'location' => $googleMeeting['location'] ?? null,
                            'status' => 'scheduled',
                            'integration_provider' => 'google',
                            'integration_data' => [
                                'meeting_id' => $googleMeeting['id'],
                                'link' => $googleMeeting['link'] ?? null,
                                'calendar_id' => $googleMeeting['calendar_id'] ?? null,
                            ],
                            'attendees' => $googleMeeting['attendees'] ?? null,
                            'tenant_id' => $tenantId,
                        ]);

                        $synced[] = $meeting;
                    }

                } catch (\Exception $e) {
                    $errors[] = [
                        'meeting_id' => $googleMeeting['id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ];
                    Log::error('Failed to sync Google meeting', [
                        'google_meeting' => $googleMeeting,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            DB::commit();

            Log::info('Google meetings sync completed', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'synced_count' => count($synced),
                'error_count' => count($errors)
            ]);

            return [
                'synced' => $synced,
                'errors' => $errors,
                'total_synced' => count($synced),
                'total_errors' => count($errors)
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to sync Google meetings', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Sync meetings from Outlook Calendar.
     */
    public function syncFromOutlook(int $userId, int $tenantId, array $outlookMeetings): array
    {
        $synced = [];
        $errors = [];

        try {
            DB::beginTransaction();

            foreach ($outlookMeetings as $outlookMeeting) {
                try {
                    // Find or create contact
                    $contact = $this->findOrCreateContactFromOutlookMeeting($outlookMeeting, $tenantId);

                    // Check if meeting already exists
                    $existingMeeting = Meeting::where('integration_provider', 'outlook')
                        ->where('integration_data->meeting_id', $outlookMeeting['id'])
                        ->where('tenant_id', $tenantId)
                        ->first();

                    if ($existingMeeting) {
                        // Update existing meeting
                        $existingMeeting->update([
                            'title' => $outlookMeeting['subject'],
                            'description' => $outlookMeeting['body'] ?? null,
                            'scheduled_at' => $outlookMeeting['start_time'],
                            'duration_minutes' => $outlookMeeting['duration_minutes'],
                            'location' => $outlookMeeting['location'] ?? null,
                            'integration_data' => [
                                'meeting_id' => $outlookMeeting['id'],
                                'link' => $outlookMeeting['link'] ?? null,
                                'calendar_id' => $outlookMeeting['calendar_id'] ?? null,
                            ],
                            'attendees' => $outlookMeeting['attendees'] ?? null,
                        ]);

                        $synced[] = $existingMeeting;
                    } else {
                        // Create new meeting
                        $meeting = Meeting::create([
                            'title' => $outlookMeeting['subject'],
                            'description' => $outlookMeeting['body'] ?? null,
                            'contact_id' => $contact->id,
                            'user_id' => $userId,
                            'scheduled_at' => $outlookMeeting['start_time'],
                            'duration_minutes' => $outlookMeeting['duration_minutes'],
                            'location' => $outlookMeeting['location'] ?? null,
                            'status' => 'scheduled',
                            'integration_provider' => 'outlook',
                            'integration_data' => [
                                'meeting_id' => $outlookMeeting['id'],
                                'link' => $outlookMeeting['link'] ?? null,
                                'calendar_id' => $outlookMeeting['calendar_id'] ?? null,
                            ],
                            'attendees' => $outlookMeeting['attendees'] ?? null,
                            'tenant_id' => $tenantId,
                        ]);

                        $synced[] = $meeting;
                    }

                } catch (\Exception $e) {
                    $errors[] = [
                        'meeting_id' => $outlookMeeting['id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ];
                    Log::error('Failed to sync Outlook meeting', [
                        'outlook_meeting' => $outlookMeeting,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            DB::commit();

            Log::info('Outlook meetings sync completed', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'synced_count' => count($synced),
                'error_count' => count($errors)
            ]);

            return [
                'synced' => $synced,
                'errors' => $errors,
                'total_synced' => count($synced),
                'total_errors' => count($errors)
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to sync Outlook meetings', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get upcoming meetings for a user.
     */
    public function getUpcomingMeetings(int $userId, int $tenantId, int $limit = 10): array
    {
        $meetings = Meeting::forTenant($tenantId)
            ->forUser($userId)
            ->upcoming()
            ->with(['contact:id,first_name,last_name,email'])
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get();

        return $meetings->map(function ($meeting) {
            return [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'contact_name' => $meeting->contact ? $meeting->contact->first_name . ' ' . $meeting->contact->last_name : 'Unknown',
                'contact_email' => $meeting->contact->email ?? null,
                'scheduled_at' => $meeting->scheduled_at->toISOString(),
                'duration_minutes' => $meeting->duration_minutes,
                'location' => $meeting->location,
                'provider' => $meeting->integration_provider,
                'link' => $meeting->getMeetingLink(),
                'summary' => $meeting->getSummary(),
            ];
        })->toArray();
    }

    /**
     * Get meetings for a specific date range.
     */
    public function getMeetingsInDateRange(int $tenantId, string $startDate, string $endDate): array
    {
        $meetings = Meeting::forTenant($tenantId)
            ->inDateRange($startDate, $endDate)
            ->with(['contact:id,first_name,last_name,email', 'user:id,name'])
            ->orderBy('scheduled_at')
            ->get();

        return $meetings->map(function ($meeting) {
            return [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'contact_name' => $meeting->contact ? $meeting->contact->first_name . ' ' . $meeting->contact->last_name : 'Unknown',
                'user_name' => $meeting->user ? $meeting->user->name : 'Unknown',
                'scheduled_at' => $meeting->scheduled_at->toISOString(),
                'duration_minutes' => $meeting->duration_minutes,
                'status' => $meeting->status,
                'provider' => $meeting->integration_provider,
                'link' => $meeting->getMeetingLink(),
            ];
        })->toArray();
    }

    /**
     * Update meeting status.
     */
    public function updateMeetingStatus(int $meetingId, int $tenantId, string $status, string $notes = null): Meeting
    {
        $meeting = Meeting::forTenant($tenantId)->findOrFail($meetingId);

        switch ($status) {
            case 'completed':
                $meeting->markAsCompleted($notes);
                break;
            case 'cancelled':
                $meeting->cancel($notes);
                break;
            case 'no_show':
                $meeting->markAsNoShow($notes);
                break;
            default:
                $meeting->update(['status' => $status]);
        }

        // Create activity record
        $this->createMeetingActivity($meeting, 'status_updated', ['new_status' => $status]);

        Log::info('Meeting status updated', [
            'meeting_id' => $meeting->id,
            'new_status' => $status,
            'tenant_id' => $tenantId
        ]);

        return $meeting;
    }

    /**
     * Find or create contact from Google meeting data.
     */
    private function findOrCreateContactFromGoogleMeeting(array $googleMeeting, int $tenantId): Contact
    {
        // Try to find contact by email from attendees
        if (isset($googleMeeting['attendees']) && is_array($googleMeeting['attendees'])) {
            foreach ($googleMeeting['attendees'] as $attendee) {
                if (isset($attendee['email']) && $attendee['email'] !== '') {
                    $contact = Contact::forTenant($tenantId)
                        ->where('email', $attendee['email'])
                        ->first();
                    
                    if ($contact) {
                        return $contact;
                    }
                }
            }
        }

        // Create a new contact if not found
        $email = $googleMeeting['attendees'][0]['email'] ?? 'unknown@example.com';
        $name = $googleMeeting['attendees'][0]['name'] ?? 'Unknown Contact';
        $nameParts = explode(' ', $name, 2);

        return Contact::create([
            'first_name' => $nameParts[0] ?? 'Unknown',
            'last_name' => $nameParts[1] ?? '',
            'email' => $email,
            'tenant_id' => $tenantId,
            'owner_id' => 1, // Default owner
        ]);
    }

    /**
     * Find or create contact from Outlook meeting data.
     */
    private function findOrCreateContactFromOutlookMeeting(array $outlookMeeting, int $tenantId): Contact
    {
        // Try to find contact by email from attendees
        if (isset($outlookMeeting['attendees']) && is_array($outlookMeeting['attendees'])) {
            foreach ($outlookMeeting['attendees'] as $attendee) {
                if (isset($attendee['emailAddress']['address'])) {
                    $contact = Contact::forTenant($tenantId)
                        ->where('email', $attendee['emailAddress']['address'])
                        ->first();
                    
                    if ($contact) {
                        return $contact;
                    }
                }
            }
        }

        // Create a new contact if not found
        $email = $outlookMeeting['attendees'][0]['emailAddress']['address'] ?? 'unknown@example.com';
        $name = $outlookMeeting['attendees'][0]['emailAddress']['name'] ?? 'Unknown Contact';
        $nameParts = explode(' ', $name, 2);

        return Contact::create([
            'first_name' => $nameParts[0] ?? 'Unknown',
            'last_name' => $nameParts[1] ?? '',
            'email' => $email,
            'tenant_id' => $tenantId,
            'owner_id' => 1, // Default owner
        ]);
    }

    /**
     * Create activity record for meeting.
     */
    private function createMeetingActivity(Meeting $meeting, string $action, array $metadata = []): void
    {
        // This would integrate with the existing activities system
        // For now, we'll just log it
        Log::info('Meeting activity created', [
            'meeting_id' => $meeting->id,
            'action' => $action,
            'metadata' => $metadata
        ]);
    }
}
