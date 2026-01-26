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
    private string $redirectUri;
    private string $baseUrl = 'https://api.zoom.us/v2';

    public function __construct()
    {
        $this->apiKey = config('services.zoom.client_id') ?? '';
        $this->apiSecret = config('services.zoom.client_secret') ?? '';
        $this->redirectUri = config('services.zoom.redirect_uri') ?: 'http://localhost:8000/api/meetings/oauth/zoom/callback';
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
        
        // For user OAuth, we only need client_id, client_secret, and redirect_uri
        // account_id is only needed for server-to-server OAuth (backward compatibility)
        return !empty($this->apiKey) && !empty($this->apiSecret) && !empty($this->redirectUri);
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
     * Create a Zoom meeting (for Meeting model, not Event).
     * This method supports user OAuth tokens.
     */
    public function createMeeting(array $data): array
    {
        // If Zoom integration is disabled, return error
        if (!$this->isEnabled()) {
            Log::info('Zoom integration disabled', [
                'title' => $data['title'] ?? 'Meeting'
            ]);
            return [
                'success' => false,
                'auth_required' => false,
                'message' => 'Zoom integration is currently disabled. Please enable it in configuration.',
                'type' => 'zoom_meeting',
                'created_at' => now()->toISOString(),
            ];
        }

        if (!$this->isConfigured()) {
            Log::warning('Zoom integration enabled but not configured', [
                'title' => $data['title'] ?? 'Meeting'
            ]);
            return [
                'success' => false,
                'auth_required' => false,
                'message' => 'Zoom integration is not properly configured.',
                'type' => 'zoom_meeting',
                'created_at' => now()->toISOString(),
            ];
        }

        // Check if user has Zoom OAuth tokens
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!$user) {
            Log::warning('User not authenticated', [
                'title' => $data['title'] ?? 'Meeting'
            ]);
            return [
                'success' => false,
                'auth_required' => true,
                'message' => 'Zoom account not connected. Please connect your Zoom account first.',
                'type' => 'zoom_meeting',
                'created_at' => now()->toISOString(),
            ];
        }

        $tenantId = $user->tenant_id ?? $user->id;
        $accessToken = $this->getUserAccessToken($user->id, $tenantId);
        
        if (!$accessToken) {
            Log::warning('User not authenticated with Zoom, checking for expired token', [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'title' => $data['title'] ?? 'Meeting'
            ]);
            
            // Check for expired token that can be refreshed
            $storedToken = \App\Models\ZoomOAuthToken::where('user_id', $user->id)
                ->where('tenant_id', $tenantId)
                ->first();
                
            if ($storedToken && !empty($storedToken->refresh_token)) {
                Log::info('Attempting to refresh expired Zoom token', [
                    'user_id' => $user->id,
                    'tenant_id' => $tenantId
                ]);
                
                $tokenData = $this->refreshAccessToken($storedToken->refresh_token);
                if ($tokenData && isset($tokenData['access_token'])) {
                    $storedToken->update([
                        'access_token' => $tokenData['access_token'],
                        'refresh_token' => $tokenData['refresh_token'] ?? $storedToken->refresh_token,
                        'expires_at' => now()->addSeconds($tokenData['expires_in'] ?? 3600)
                    ]);
                    $accessToken = $tokenData['access_token'];
                } else {
                    Log::warning('Failed to refresh token, user needs to re-authenticate');
                    return [
                        'success' => false,
                        'auth_required' => true,
                        'message' => 'Zoom authentication expired. Please reconnect your Zoom account.',
                        'type' => 'zoom_meeting',
                        'created_at' => now()->toISOString(),
                    ];
                }
            } else {
                Log::warning('No valid OAuth token found, user needs to authenticate');
                return [
                    'success' => false,
                    'auth_required' => true,
                    'message' => 'Zoom account not connected. Please connect your Zoom account first.',
                    'type' => 'zoom_meeting',
                    'created_at' => now()->toISOString(),
                ];
            }
        }

        try {
            // Create real Zoom meeting
            Log::info('Creating real Zoom meeting', [
                'title' => $data['title'] ?? 'Meeting',
                'scheduled_at' => $data['scheduled_at'] ?? 'now',
                'user_id' => $user->id
            ]);

            return $this->createZoomMeeting($data, $accessToken);

        } catch (\Exception $e) {
            Log::error('Failed to create real Zoom meeting', [
                'title' => $data['title'] ?? 'Meeting',
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? 'N/A'
            ]);

            return [
                'success' => false,
                'auth_required' => false,
                'message' => 'Failed to create Zoom meeting: ' . $e->getMessage(),
                'type' => 'zoom_meeting',
                'created_at' => now()->toISOString(),
            ];
        }
    }

    /**
     * Create a Zoom meeting via API.
     */
    private function createZoomMeeting(array $data, string $accessToken): array
    {
        $startDateTime = \Carbon\Carbon::parse($data['scheduled_at'])->toRfc3339String();
        $durationMinutes = $data['duration_minutes'] ?? 30;

        $meetingData = [
            'topic' => $data['title'] ?? 'Meeting',
            'type' => 2, // Scheduled meeting
            'start_time' => $startDateTime,
            'duration' => $durationMinutes,
            'timezone' => config('app.timezone', 'UTC'),
            'agenda' => $data['description'] ?? '',
            'settings' => [
                'host_video' => true,
                'participant_video' => true,
                'join_before_host' => false,
                'mute_upon_entry' => true,
                'waiting_room' => true,
            ],
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/users/me/meetings', $meetingData);

        if ($response->successful()) {
            $meeting = $response->json();
            
            Log::info('Zoom meeting created successfully', [
                'meeting_id' => $meeting['id'],
                'join_url' => $meeting['join_url']
            ]);

            return [
                'success' => true,
                'meeting_id' => (string)$meeting['id'],
                'join_url' => $meeting['join_url'],
                'meeting_url' => $meeting['join_url'],
                'password' => $meeting['password'] ?? null,
                'start_url' => $meeting['start_url'] ?? null,
                'type' => 'zoom_meeting',
                'created_at' => now()->toISOString(),
            ];
        } else {
            $errorResponse = $response->json();
            $errorMessage = $errorResponse['message'] ?? $response->body();
            
            Log::error('Failed to create Zoom meeting', [
                'status' => $response->status(),
                'error' => $errorMessage,
                'response' => $response->body()
            ]);

            throw new \Exception('Failed to create Zoom meeting: ' . $errorMessage);
        }
    }

    /**
     * Create a Zoom meeting for an event (backward compatibility).
     */
    public function createMeetingForEvent(Event $event): array
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
            // Try user OAuth first, then fall back to server-to-server
            $user = \Illuminate\Support\Facades\Auth::user();
            $accessToken = null;
            
            if ($user) {
                $tenantId = $user->tenant_id ?? $user->id;
                $accessToken = $this->getUserAccessToken($user->id, $tenantId);
            }
            
            // Fall back to server-to-server OAuth if user OAuth not available
            if (!$accessToken) {
                $accessToken = $this->getAccessToken();
            }

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
     * Get OAuth authorization URL for user OAuth flow.
     * 
     * @param \App\Models\User|null $user Optional user to include in state parameter
     * @return string
     */
    public function getAuthorizationUrl($user = null): string
    {
        $scopes = [
            'meeting:write:meeting',
            'meeting:read:meeting',
            'user:read:user',
        ];

        // Generate state with user_id and tenant_id
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
            session(['zoom_oauth_state' => $state]);
        }

        $params = [
            'client_id' => $this->apiKey,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri,
            'scope' => implode(' ', $scopes),
            'state' => $state,
        ];

        return 'https://zoom.us/oauth/authorize?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token.
     */
    public function exchangeCodeForToken(string $code, int $userId = null, int $tenantId = null): array
    {
        try {
            $response = Http::asForm()->post('https://zoom.us/oauth/token', [
                'client_id' => $this->apiKey,
                'client_secret' => $this->apiSecret,
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
                ];
            }

            Log::error('Failed to exchange Zoom authorization code', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [
                'success' => false,
                'error' => 'Failed to exchange authorization code',
                'message' => $response->body()
            ];

        } catch (\Exception $e) {
            Log::error('Exception exchanging Zoom authorization code', [
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
            $response = Http::asForm()->post('https://zoom.us/oauth/token', [
                'client_id' => $this->apiKey,
                'client_secret' => $this->apiSecret,
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
                'redirect_uri' => $this->redirectUri,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Failed to refresh Zoom access token', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('Exception refreshing Zoom access token', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Store OAuth token in database.
     */
    public function storeToken(array $tokenData, int $userId, int $tenantId): void
    {
        // Get user email from Zoom API if available
        $email = null;
        if (isset($tokenData['access_token'])) {
            try {
                $userResponse = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $tokenData['access_token'],
                ])->get('https://api.zoom.us/v2/users/me');
                
                if ($userResponse->successful()) {
                    $userData = $userResponse->json();
                    $email = $userData['email'] ?? null;
                }
            } catch (\Exception $e) {
                Log::warning('Failed to fetch Zoom user email', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        \App\Models\ZoomOAuthToken::updateOrCreate(
            [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
            ],
            [
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'expires_at' => isset($tokenData['expires_in']) 
                    ? now()->addSeconds($tokenData['expires_in']) 
                    : now()->addHour(),
                'email' => $email,
            ]
        );
    }

    /**
     * Get access token for Zoom API (user OAuth version).
     * This method checks for user OAuth tokens first, then falls back to server-to-server.
     */
    private function getUserAccessToken(int $userId, int $tenantId): ?string
    {
        // Check for valid user OAuth token
        $token = \App\Models\ZoomOAuthToken::getValidTokenForUser($userId, $tenantId);
        
        if ($token) {
            return $token->access_token;
        }
        
        // Try to refresh expired token
        $storedToken = \App\Models\ZoomOAuthToken::where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->first();
            
        if ($storedToken && !empty($storedToken->refresh_token)) {
            Log::info('Attempting to refresh expired Zoom token', [
                'user_id' => $userId,
                'tenant_id' => $tenantId
            ]);
            
            $tokenData = $this->refreshAccessToken($storedToken->refresh_token);
            if ($tokenData && isset($tokenData['access_token'])) {
                $storedToken->update([
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'] ?? $storedToken->refresh_token,
                    'expires_at' => now()->addSeconds($tokenData['expires_in'] ?? 3600)
                ]);
                return $tokenData['access_token'];
            }
        }
        
        return null;
    }

    /**
     * Get access token for Zoom API (server-to-server OAuth - backward compatibility).
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
