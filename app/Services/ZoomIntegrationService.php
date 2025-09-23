<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ZoomIntegrationService
{
    private string $apiKey;
    private string $apiSecret;
    private string $baseUrl = 'https://api.zoom.us/v2';

    public function __construct()
    {
        $this->apiKey = config('services.zoom.client_id') ?? '';
        $this->apiSecret = config('services.zoom.client_secret') ?? '';
    }

    /**
     * Check if Zoom integration is enabled.
     */
    public function isEnabled(): bool
    {
        return config('services.zoom.enabled', false);
    }

    /**
     * Check if Zoom integration is properly configured.
     */
    public function isConfigured(): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }
        
        $accountId = config('services.zoom.account_id');
        return !empty($this->apiKey) && !empty($this->apiSecret) && !empty($accountId);
    }

    /**
     * Generate mock Zoom meeting data for testing.
     */
    private function generateMockMeetingData(Event $event): array
    {
        $mockMeetingId = 'mock_' . $event->id . '_' . time();
        $mockJoinUrl = 'https://zoom.us/j/' . $mockMeetingId;
        $mockStartUrl = 'https://zoom.us/s/' . $mockMeetingId;
        $mockPassword = 'mock' . rand(1000, 9999);

        Log::info('Generating mock Zoom meeting data', [
            'event_id' => $event->id,
            'mock_meeting_id' => $mockMeetingId
        ]);

        return [
            'success' => true,
            'meeting_id' => $mockMeetingId,
            'join_url' => $mockJoinUrl,
            'password' => $mockPassword,
            'start_url' => $mockStartUrl,
            'meeting_data' => [
                'id' => $mockMeetingId,
                'join_url' => $mockJoinUrl,
                'start_url' => $mockStartUrl,
                'password' => $mockPassword,
                'topic' => $event->name,
                'type' => 2,
                'start_time' => $event->scheduled_at->toISOString(),
                'duration' => $event->settings['duration'] ?? 60,
            ],
        ];
    }

    /**
     * Create a Zoom meeting for an event.
     */
    public function createMeeting(Event $event): array
    {
        // If Zoom integration is disabled, return mock data
        if (!$this->isEnabled()) {
            Log::info('Zoom integration disabled, using mock data', [
                'event_id' => $event->id
            ]);
            return $this->generateMockMeetingData($event);
        }

        if (!$this->isConfigured()) {
            Log::warning('Zoom integration enabled but not configured, using mock data', [
                'event_id' => $event->id
            ]);
            return $this->generateMockMeetingData($event);
        }

        try {
            $accessToken = $this->getAccessToken();

            $meetingData = [
                'topic' => $event->name,
                'type' => 2, // Scheduled meeting
                'start_time' => $event->scheduled_at->toISOString(),
                'duration' => $event->settings['duration'] ?? 60,
                'timezone' => config('app.timezone', 'UTC'),
                'agenda' => $event->description ?? '',
                'settings' => [
                    'host_video' => true,
                    'participant_video' => true,
                    'join_before_host' => false,
                    'mute_upon_entry' => true,
                    'waiting_room' => $event->settings['waiting_room'] ?? true,
                    'recording' => [
                        'auto_recording' => $event->settings['recording_enabled'] ? 'cloud' : 'none',
                    ],
                    'registrants_confirmation_email' => true,
                ],
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/users/me/meetings', $meetingData);

            if ($response->successful()) {
                $meeting = $response->json();
                
                Log::info('Zoom meeting created successfully', [
                    'event_id' => $event->id,
                    'meeting_id' => $meeting['id'],
                    'join_url' => $meeting['join_url']
                ]);

                return [
                    'success' => true,
                    'meeting_id' => $meeting['id'],
                    'join_url' => $meeting['join_url'],
                    'password' => $meeting['password'],
                    'start_url' => $meeting['start_url'] ?? null,
                    'meeting_data' => $meeting,
                ];
                } else {
                    Log::error('Failed to create Zoom meeting, falling back to mock data', [
                        'event_id' => $event->id,
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);

                    // Fall back to mock data if Zoom API fails
                    return $this->generateMockMeetingData($event);
                }
            } catch (\Exception $e) {
                Log::error('Exception creating Zoom meeting, falling back to mock data', [
                    'event_id' => $event->id,
                    'error' => $e->getMessage()
                ]);

                // Fall back to mock data if Zoom API fails
                return $this->generateMockMeetingData($event);
            }
    }

    /**
     * Update a Zoom meeting.
     */
    public function updateMeeting(Event $event, string $meetingId): array
    {
        if (!$this->isEnabled()) {
            Log::info('Zoom integration disabled, skipping update', [
                'event_id' => $event->id,
                'meeting_id' => $meetingId
            ]);
            return ['success' => true, 'message' => 'Mock mode - update skipped'];
        }

        if (!$this->isConfigured()) {
            Log::warning('Zoom integration enabled but not configured, skipping update', [
                'event_id' => $event->id,
                'meeting_id' => $meetingId
            ]);
            return ['success' => true, 'message' => 'Mock mode - update skipped'];
        }

        try {
            $accessToken = $this->getAccessToken();

            $meetingData = [
                'topic' => $event->name,
                'start_time' => $event->scheduled_at->toISOString(),
                'duration' => $event->settings['duration'] ?? 60,
                'timezone' => config('app.timezone', 'UTC'),
                'agenda' => $event->description ?? '',
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->patch($this->baseUrl . "/meetings/{$meetingId}", $meetingData);

            if ($response->successful()) {
                Log::info('Zoom meeting updated successfully', [
                    'event_id' => $event->id,
                    'meeting_id' => $meetingId
                ]);

                return ['success' => true];
            } else {
                Log::error('Failed to update Zoom meeting', [
                    'event_id' => $event->id,
                    'meeting_id' => $meetingId,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);

                return [
                    'success' => false,
                    'error' => 'Failed to update Zoom meeting: ' . $response->body(),
                ];
            }
        } catch (\Exception $e) {
            Log::error('Exception updating Zoom meeting', [
                'event_id' => $event->id,
                'meeting_id' => $meetingId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Delete a Zoom meeting.
     */
    public function deleteMeeting(string $meetingId): array
    {
        if (!$this->isEnabled()) {
            Log::info('Zoom integration disabled, skipping delete', [
                'meeting_id' => $meetingId
            ]);
            return ['success' => true, 'message' => 'Mock mode - delete skipped'];
        }

        if (!$this->isConfigured()) {
            Log::warning('Zoom integration enabled but not configured, skipping delete', [
                'meeting_id' => $meetingId
            ]);
            return ['success' => true, 'message' => 'Mock mode - delete skipped'];
        }

        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->delete($this->baseUrl . "/meetings/{$meetingId}");

            if ($response->successful()) {
                Log::info('Zoom meeting deleted successfully', [
                    'meeting_id' => $meetingId
                ]);

                return ['success' => true];
            } else {
                Log::error('Failed to delete Zoom meeting', [
                    'meeting_id' => $meetingId,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);

                return [
                    'success' => false,
                    'error' => 'Failed to delete Zoom meeting: ' . $response->body(),
                ];
            }
        } catch (\Exception $e) {
            Log::error('Exception deleting Zoom meeting', [
                'meeting_id' => $meetingId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get meeting participants.
     */
    public function getMeetingParticipants(string $meetingId): array
    {
        if (!$this->isEnabled()) {
            Log::info('Zoom integration disabled, returning mock participants', [
                'meeting_id' => $meetingId
            ]);
            return [
                'success' => true,
                'participants' => [
                    'registrants' => [],
                    'total_records' => 0
                ]
            ];
        }

        if (!$this->isConfigured()) {
            Log::warning('Zoom integration enabled but not configured, returning mock participants', [
                'meeting_id' => $meetingId
            ]);
            return [
                'success' => true,
                'participants' => [
                    'registrants' => [],
                    'total_records' => 0
                ]
            ];
        }

        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->get($this->baseUrl . "/meetings/{$meetingId}/registrants");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'participants' => $response->json(),
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to get participants: ' . $response->body(),
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get access token for Zoom API.
     */
    private function getAccessToken(): string
    {
        return Cache::remember('zoom_access_token', 3300, function () {
            $accountId = config('services.zoom.account_id');
            
            Log::info('Getting Zoom access token', [
                'account_id' => $accountId,
                'client_id' => $this->apiKey,
                'client_secret_length' => strlen($this->apiSecret)
            ]);
            
            // Use Basic Authentication for Zoom Server-to-Server OAuth
            $credentials = base64_encode($this->apiKey . ':' . $this->apiSecret);
            
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $credentials,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->asForm()->post('https://zoom.us/oauth/token', [
                'grant_type' => 'account_credentials',
                'account_id' => $accountId,
            ]);

            Log::info('Zoom OAuth response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['access_token'];
            }

            throw new \Exception('Failed to get Zoom access token: ' . $response->body());
        });
    }

    /**
     * Verify webhook signature.
     */
    public function verifyWebhookSignature(string $payload, string $signature, string $timestamp): bool
    {
        $webhookSecret = config('services.zoom.webhook_secret');
        $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);
        
        return hash_equals($expectedSignature, $signature);
    }
}
