<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TeamsIntegrationService
{
    private ?string $clientId;
    private ?string $clientSecret;
    private ?string $tenantId;
    private ?string $redirectUri;
    private string $baseUrl = 'https://graph.microsoft.com/v1.0';

    public function __construct()
    {
        $this->clientId = config('services.teams.client_id');
        $this->clientSecret = config('services.teams.client_secret');
        $this->tenantId = config('services.teams.tenant_id');
        $this->redirectUri = config('services.teams.redirect_uri') ?: 'http://localhost:8000/api/meetings/oauth/teams/callback';
    }

    /**
     * Check if Teams integration is enabled.
     */
    public function isEnabled(): bool
    {
        return config('services.teams.enabled', false);
    }

    /**
     * Check if Teams integration is properly configured.
     */
    public function isConfigured(): bool
    {
        // tenantId is optional now (we use /common/ endpoint)
        return !empty($this->clientId) && !empty($this->clientSecret);
    }

    /**
     * Get authorization URL for Teams OAuth.
     */
    public function getAuthorizationUrl($user = null): string
    {
        $scopes = [
            'https://graph.microsoft.com/Calendars.ReadWrite',
            'https://graph.microsoft.com/OnlineMeetings.ReadWrite',
            'offline_access'
        ];

        // Generate state with user_id and tenant_id (like Google)
        $state = csrf_token();
        if ($user) {
            $stateData = [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id ?? $user->id,
                'nonce' => bin2hex(random_bytes(16)),
                'timestamp' => time()
            ];
            $state = base64_encode(json_encode($stateData));
            
            // Store in session for validation (optional)
            session(['teams_oauth_state' => $state]);
        }

        $params = [
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri,
            'scope' => implode(' ', $scopes),
            'state' => $state,
        ];

        // Use /common/ instead of /{tenantId}/ to support both personal and work accounts
        return 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token.
     */
    public function exchangeCodeForToken(string $code, int $userId = null, int $tenantId = null): array
    {
        try {
            // Use /common/ instead of /{tenantId}/ to support both personal and work accounts
            $response = Http::asForm()->post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->redirectUri,
            ]);

            if ($response->successful()) {
                $tokenData = $response->json();
                
                // Store token in database with user_id and tenant_id
                if ($userId && $tenantId) {
                    $this->storeToken($tokenData, $userId, $tenantId);
                } else {
                    // Fallback: try to get user from auth (for backward compatibility)
                    $user = \Illuminate\Support\Facades\Auth::user();
                    if ($user) {
                        $this->storeToken($tokenData, $user->id, $user->tenant_id ?? $user->id);
                    }
                }
                
                return [
                    'success' => true,
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'] ?? null,
                    'expires_in' => $tokenData['expires_in'] ?? 3600,
                    'email' => $tokenData['email'] ?? null,
                ];
            }

            Log::error('Failed to exchange Teams authorization code', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [
                'success' => false,
                'error' => 'Failed to exchange authorization code',
                'message' => $response->body()
            ];

        } catch (\Exception $e) {
            Log::error('Exception exchanging Teams authorization code', [
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
     * Refresh access token using refresh token.
     * Returns full token data array or null on failure.
     */
    public function refreshAccessToken(string $refreshToken): ?array
    {
        try {
            $response = Http::asForm()->post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
                'redirect_uri' => $this->redirectUri,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Failed to refresh Teams access token', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Exception refreshing Teams access token', [
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Create a real Teams meeting.
     */
    public function createMeeting(array $data): array
    {
        // If Teams integration is disabled, return mock data
        if (!$this->isEnabled()) {
            Log::info('Teams integration disabled, using mock data', [
                'title' => $data['title'] ?? 'Meeting'
            ]);
            return $this->generateMockTeamsData($data);
        }

        if (!$this->isConfigured()) {
            Log::warning('Teams integration enabled but not configured, using mock data', [
                'title' => $data['title'] ?? 'Meeting'
            ]);
            return $this->generateMockTeamsData($data);
        }

        $user = \Illuminate\Support\Facades\Auth::user();
        if (!$user) {
            Log::warning('User not authenticated, using mock data', [
                'title' => $data['title'] ?? 'Meeting'
            ]);
            return $this->generateMockTeamsData($data);
        }

        $tenantId = $user->tenant_id ?? $user->id;
        $teamsToken = \App\Models\TeamsOAuthToken::getValidTokenForUser($user->id, $tenantId);
        if (!$teamsToken) {
            Log::warning('User not authenticated with Teams, checking for expired token', [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'title' => $data['title'] ?? 'Meeting'
            ]);
            
            // Check for expired token that can be refreshed
            $storedToken = \App\Models\TeamsOAuthToken::where('user_id', $user->id)
                ->where('tenant_id', $tenantId)
                ->first();
            if ($storedToken && !empty($storedToken->access_token) && !empty($storedToken->refresh_token)) {
                Log::info('Attempting to refresh expired Teams OAuth token', [
                    'user_id' => $user->id,
                    'tenant_id' => $tenantId
                ]);
                
                $tokenData = $this->refreshAccessToken($storedToken->refresh_token);
                if ($tokenData && isset($tokenData['access_token'])) {
                    // Update the token in database
                    $storedToken->update([
                        'access_token' => $tokenData['access_token'],
                        'expires_at' => now()->addSeconds($tokenData['expires_in'] ?? 3600)
                    ]);
                    
                    try {
                        return $this->createTeamsMeeting($data, $tokenData['access_token']);
                    } catch (\Exception $e) {
                        Log::error('Failed to create real Teams meeting with refreshed token', [
                            'error' => $e->getMessage()
                        ]);
                        return $this->generateMockTeamsData($data);
                    }
                } else {
                    Log::warning('Failed to refresh Teams token, user needs to re-authenticate');
                    return $this->generateMockTeamsData($data);
                }
            } else {
                Log::warning('No valid OAuth token found, user needs to authenticate');
                return $this->generateMockTeamsData($data);
            }
        }

        try {
            // Create real Teams meeting
            Log::info('Creating real Teams meeting', [
                'title' => $data['title'] ?? 'Meeting',
                'scheduled_at' => $data['scheduled_at'] ?? 'now',
                'user_id' => $user->id
            ]);

            return $this->createTeamsMeeting($data, $teamsToken->access_token);

        } catch (\Exception $e) {
            Log::error('Failed to create real Teams meeting, falling back to mock data', [
                'title' => $data['title'] ?? 'Meeting',
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? 'N/A'
            ]);

            return $this->generateMockTeamsData($data);
        }
    }

    /**
     * Create a real Teams meeting via Microsoft Graph API.
     */
    public function createTeamsMeeting(array $data, string $accessToken): array
    {
        $startDateTime = \Carbon\Carbon::parse($data['scheduled_at'])->toRfc3339String();
        $endDateTime = $this->calculateEndTime($data['scheduled_at'], $data['duration_minutes'] ?? 30);

        $meetingData = [
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
            'isOnlineMeeting' => true,
            'onlineMeetingProvider' => 'teamsForBusiness',
            'attendees' => [
                [
                    'emailAddress' => [
                        'address' => $data['contact_email'] ?? 'attendee@example.com',
                        'name' => $data['contact_name'] ?? 'Attendee'
                    ],
                    'type' => 'required'
                ]
            ]
        ];

        Log::info('Sending request to Microsoft Graph API for Teams meeting', [
            'request_data' => $meetingData,
            'access_token_length' => strlen($accessToken)
        ]);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/me/events', $meetingData);

        if ($response->successful()) {
            $event = $response->json();
            
            Log::info('Teams meeting created successfully', [
                'event_id' => $event['id'],
                'join_url' => $event['onlineMeeting']['joinUrl'] ?? null
            ]);

            return [
                'success' => true,
                'meeting_id' => $event['id'],
                'join_url' => $event['onlineMeeting']['joinUrl'] ?? null,
                'meeting_url' => $event['onlineMeeting']['joinUrl'] ?? null,
                'type' => 'teams_meeting',
                'created_at' => now()->toISOString(),
                'meeting_code' => $this->extractMeetingCode($event['onlineMeeting']['joinUrl'] ?? null),
                'calendar_event_id' => $event['id'],
            ];
        } else {
            Log::error('Failed to create Teams meeting', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            throw new \Exception('Failed to create Teams meeting: ' . $response->body());
        }
    }

    /**
     * Generate mock Teams data when integration is disabled.
     */
    private function generateMockTeamsData(array $data): array
    {
        Log::info('Teams integration disabled, returning error instead of mock data', [
            'title' => $data['title'] ?? 'Meeting'
        ]);

        return [
            'success' => false,
            'error' => 'Teams integration disabled',
            'message' => 'Teams integration is currently disabled. Please enable it in configuration.',
            'auth_required' => false,
            'type' => 'teams_meeting',
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
     * Extract meeting code from Teams URL.
     */
    private function extractMeetingCode(?string $teamsUrl): string
    {
        if (!$teamsUrl) {
            return 'unknown';
        }
        
        if (preg_match('/meetup-join\/([a-zA-Z0-9\-]+)/i', $teamsUrl, $matches)) {
            return $matches[1];
        }
        return 'unknown';
    }

    /**
     * Store OAuth token in database.
     */
    public function storeToken(array $tokenData, int $userId, int $tenantId): void
    {
        \App\Models\TeamsOAuthToken::updateOrCreate(
            [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
            ],
            [
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'expires_at' => now()->addSeconds($tokenData['expires_in'] ?? 3600),
                'scope' => $tokenData['scope'] ?? null,
                'email' => $tokenData['email'] ?? null,
            ]
        );
    }
}
