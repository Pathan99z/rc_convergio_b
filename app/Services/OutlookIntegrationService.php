<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class OutlookIntegrationService
{
    private ?string $clientId;
    private ?string $clientSecret;
    private ?string $tenantId;
    private ?string $redirectUri;
    private string $baseUrl = 'https://graph.microsoft.com/v1.0';

    public function __construct()
    {
        $this->clientId = config('services.outlook.client_id');
        $this->clientSecret = config('services.outlook.client_secret');
        $this->tenantId = config('services.outlook.tenant_id');
        $this->redirectUri = config('services.outlook.redirect_uri') ?: 'http://localhost:8000/api/meetings/oauth/outlook/callback';
    }

    /**
     * Check if Outlook integration is enabled.
     */
    public function isEnabled(): bool
    {
        return config('services.outlook.enabled', false);
    }

    /**
     * Check if Outlook integration is properly configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->clientId) && !empty($this->clientSecret) && !empty($this->tenantId);
    }

    /**
     * Get authorization URL for Outlook OAuth.
     */
    public function getAuthorizationUrl(): string
    {
        $scopes = [
            'https://graph.microsoft.com/Calendars.ReadWrite',
            'https://graph.microsoft.com/OnlineMeetings.ReadWrite',
            'offline_access'
        ];

        $params = [
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri,
            'scope' => implode(' ', $scopes),
            'state' => csrf_token(),
        ];

        return 'https://login.microsoftonline.com/' . $this->tenantId . '/oauth2/v2.0/authorize?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token.
     */
    public function exchangeCodeForToken(string $code): array
    {
        try {
            $response = Http::post('https://login.microsoftonline.com/' . $this->tenantId . '/oauth2/v2.0/token', [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->redirectUri,
            ]);

            if ($response->successful()) {
                $tokenData = $response->json();
                
                // Store token in database
                $this->storeToken($tokenData);
                
                return [
                    'success' => true,
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'] ?? null,
                    'expires_in' => $tokenData['expires_in'] ?? 3600,
                ];
            }

            Log::error('Failed to exchange Outlook authorization code', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [
                'success' => false,
                'error' => 'Failed to exchange authorization code',
                'message' => $response->body()
            ];

        } catch (\Exception $e) {
            Log::error('Exception exchanging Outlook authorization code', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Exception occurred',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Create a real Outlook calendar event.
     */
    public function createMeeting(array $data): array
    {
        // If Outlook integration is disabled, return mock data
        if (!$this->isEnabled()) {
            Log::info('Outlook integration disabled, using mock data', [
                'title' => $data['title'] ?? 'Meeting'
            ]);
            return $this->generateMockOutlookData($data);
        }

        if (!$this->isConfigured()) {
            Log::warning('Outlook integration enabled but not configured, using mock data', [
                'title' => $data['title'] ?? 'Meeting'
            ]);
            return $this->generateMockOutlookData($data);
        }

        $user = \Illuminate\Support\Facades\Auth::user();
        if (!$user) {
            Log::warning('User not authenticated, using mock data', [
                'title' => $data['title'] ?? 'Meeting'
            ]);
            return $this->generateMockOutlookData($data);
        }

        $outlookToken = \App\Models\OutlookOAuthToken::getValidTokenForUser($user->id);
        if (!$outlookToken) {
            Log::warning('User not authenticated with Outlook, using mock data', [
                'user_id' => $user->id,
                'title' => $data['title'] ?? 'Meeting'
            ]);
            return $this->generateMockOutlookData($data);
        }

        try {
            // Create real Outlook calendar event
            Log::info('Creating real Outlook calendar event', [
                'title' => $data['title'] ?? 'Meeting',
                'scheduled_at' => $data['scheduled_at'] ?? 'now',
                'user_id' => $user->id
            ]);

            return $this->createOutlookEvent($data, $outlookToken->access_token);

        } catch (\Exception $e) {
            Log::error('Failed to create real Outlook event, falling back to mock data', [
                'title' => $data['title'] ?? 'Meeting',
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? 'N/A'
            ]);

            return $this->generateMockOutlookData($data);
        }
    }

    /**
     * Create a real Outlook calendar event via Microsoft Graph API.
     */
    public function createOutlookEvent(array $data, string $accessToken): array
    {
        $startDateTime = \Carbon\Carbon::parse($data['scheduled_at'])->toRfc3339String();
        $endDateTime = $this->calculateEndTime($data['scheduled_at'], $data['duration_minutes'] ?? 30);

        $eventData = [
            'subject' => $data['title'],
            'body' => [
                'content' => $data['description'] ?? '',
                'contentType' => 'text'
            ],
            'start' => [
                'dateTime' => $startDateTime,
                'timeZone' => config('app.timezone', 'UTC'),
            ],
            'end' => [
                'dateTime' => $endDateTime,
                'timeZone' => config('app.timezone', 'UTC'),
            ],
            'attendees' => [
                [
                    'emailAddress' => [
                        'address' => $data['contact_email'] ?? 'attendee@example.com',
                        'name' => $data['contact_name'] ?? 'Attendee'
                    ],
                    'type' => 'required'
                ]
            ],
            'isOnlineMeeting' => true,
            'onlineMeetingProvider' => 'teamsForBusiness'
        ];

        Log::info('Sending request to Microsoft Graph API for Outlook event', [
            'request_data' => $eventData,
            'access_token_length' => strlen($accessToken)
        ]);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/me/events', $eventData);

        if ($response->successful()) {
            $event = $response->json();
            
            Log::info('Outlook event created successfully', [
                'event_id' => $event['id'],
                'join_url' => $event['onlineMeeting']['joinUrl'] ?? null
            ]);

            return [
                'success' => true,
                'meeting_id' => $event['id'],
                'join_url' => $event['onlineMeeting']['joinUrl'] ?? null,
                'meeting_url' => $event['onlineMeeting']['joinUrl'] ?? null,
                'type' => 'outlook_event',
                'created_at' => now()->toISOString(),
                'meeting_code' => $this->extractMeetingCode($event['onlineMeeting']['joinUrl'] ?? null),
                'calendar_event_id' => $event['id'],
            ];
        } else {
            Log::error('Failed to create Outlook event', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            throw new \Exception('Failed to create Outlook event: ' . $response->body());
        }
    }

    /**
     * Generate mock Outlook data when integration is disabled.
     */
    private function generateMockOutlookData(array $data): array
    {
        Log::info('Outlook integration disabled, returning error instead of mock data', [
            'title' => $data['title'] ?? 'Meeting'
        ]);

        return [
            'success' => false,
            'error' => 'Outlook integration disabled',
            'message' => 'Outlook integration is currently disabled. Please enable it in configuration.',
            'auth_required' => false,
            'type' => 'outlook_event',
            'created_at' => now()->toISOString(),
        ];
    }

    /**
     * Calculate end time based on start time and duration.
     */
    private function calculateEndTime(string $startTime, int $durationMinutes): string
    {
        return \Carbon\Carbon::parse($startTime)
            ->addMinutes($durationMinutes)
            ->toRfc3339String();
    }

    /**
     * Extract meeting code from Outlook URL.
     */
    private function extractMeetingCode(?string $outlookUrl): string
    {
        if (!$outlookUrl) {
            return 'unknown';
        }
        
        if (preg_match('/meetup-join\/([a-zA-Z0-9\-]+)/i', $outlookUrl, $matches)) {
            return $matches[1];
        }
        return 'unknown';
    }

    /**
     * Store OAuth token in database.
     */
    private function storeToken(array $tokenData): void
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!$user) return;

        \App\Models\OutlookOAuthToken::updateOrCreate(
            ['user_id' => $user->id],
            [
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'expires_at' => now()->addSeconds($tokenData['expires_in'] ?? 3600),
                'scope' => $tokenData['scope'] ?? null,
            ]
        );
    }
}
