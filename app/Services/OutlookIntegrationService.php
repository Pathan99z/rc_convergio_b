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
        // tenantId is optional now (we use /common/ endpoint)
        return !empty($this->clientId) && !empty($this->clientSecret);
    }

    /**
     * Get authorization URL for Outlook OAuth.
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
            session(['outlook_oauth_state' => $state]);
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

            Log::error('Failed to refresh Outlook access token', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Exception refreshing Outlook access token', [
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Create a real Outlook calendar event.
     */
    public function createMeeting(array $data): array
    {
        // If Outlook integration is disabled, return error
        if (!$this->isEnabled()) {
            Log::info('Outlook integration disabled', [
                'title' => $data['title'] ?? 'Meeting'
            ]);
            return [
                'success' => false,
                'error' => 'Outlook integration disabled',
                'message' => 'Outlook integration is currently disabled. Please enable it in configuration.',
                'type' => 'outlook_event',
                'created_at' => now()->toISOString(),
            ];
        }

        if (!$this->isConfigured()) {
            Log::warning('Outlook integration enabled but not configured', [
                'title' => $data['title'] ?? 'Meeting',
                'has_client_id' => !empty($this->clientId),
                'has_client_secret' => !empty($this->clientSecret)
            ]);
            return [
                'success' => false,
                'error' => 'Outlook integration not configured',
                'message' => 'Outlook integration is enabled but not properly configured. Please check your credentials.',
                'type' => 'outlook_event',
                'created_at' => now()->toISOString(),
            ];
        }

        $user = \Illuminate\Support\Facades\Auth::user();
        if (!$user) {
            Log::warning('User not authenticated', [
                'title' => $data['title'] ?? 'Meeting'
            ]);
            return [
                'success' => false,
                'error' => 'User not authenticated',
                'message' => 'User must be authenticated to create Outlook meetings.',
                'type' => 'outlook_event',
                'created_at' => now()->toISOString(),
            ];
        }

        $tenantId = $user->tenant_id ?? $user->id;
        
        // Add detailed logging for debugging
        Log::info('Outlook meeting creation - checking token', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'user_tenant_id' => $user->tenant_id,
            'title' => $data['title'] ?? 'Meeting'
        ]);
        
        $outlookToken = \App\Models\OutlookOAuthToken::getValidTokenForUser($user->id, $tenantId);
        if (!$outlookToken) {
            Log::warning('User not authenticated with Outlook, checking for expired token', [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'title' => $data['title'] ?? 'Meeting'
            ]);
            
            // Check if any token exists at all
            $anyToken = \App\Models\OutlookOAuthToken::where('user_id', $user->id)
                ->where('tenant_id', $tenantId)
                ->first();
            
            Log::info('Token lookup details', [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'token_exists' => $anyToken ? 'yes' : 'no',
                'token_expires_at' => $anyToken?->expires_at?->toIso8601String(),
                'token_is_expired' => $anyToken ? $anyToken->isExpired() : 'N/A',
                'has_refresh_token' => $anyToken && !empty($anyToken->refresh_token) ? 'yes' : 'no'
            ]);
            
            // Check for expired token that can be refreshed
            $storedToken = \App\Models\OutlookOAuthToken::where('user_id', $user->id)
                ->where('tenant_id', $tenantId)
                ->first();
            if ($storedToken && !empty($storedToken->access_token) && !empty($storedToken->refresh_token)) {
                Log::info('Attempting to refresh expired Outlook OAuth token', [
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
                        return $this->createOutlookEvent($data, $tokenData['access_token']);
                    } catch (\Exception $e) {
                        Log::error('Failed to create real Outlook event with refreshed token', [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        return [
                            'success' => false,
                            'error' => 'Failed to create Outlook event',
                            'message' => $e->getMessage(),
                            'type' => 'outlook_event',
                            'created_at' => now()->toISOString(),
                        ];
                    }
                } else {
                    Log::warning('Failed to refresh Outlook token, user needs to re-authenticate');
                    return [
                        'success' => false,
                        'auth_required' => true,
                        'message' => 'Outlook token expired. Please reconnect your Outlook account.',
                        'type' => 'outlook_event',
                        'created_at' => now()->toISOString(),
                    ];
                }
            } else {
                Log::warning('No valid OAuth token found, user needs to authenticate');
                return [
                    'success' => false,
                    'auth_required' => true,
                    'message' => 'Outlook account not connected. Please connect your Outlook account first.',
                    'type' => 'outlook_event',
                    'created_at' => now()->toISOString(),
                ];
            }
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
            Log::error('Failed to create real Outlook event', [
                'title' => $data['title'] ?? 'Meeting',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? 'N/A'
            ]);

            // Check for account suspension or authentication errors
            $errorMessage = $e->getMessage();
            if (str_contains($errorMessage, 'suspended') || str_contains($errorMessage, 'ErrorAccountSuspend')) {
                return [
                    'success' => false,
                    'error' => 'Account suspended',
                    'message' => 'Your Microsoft account is suspended. Please check your email inbox and verify your account, then reconnect your Outlook account.',
                    'auth_required' => true,
                    'type' => 'outlook_event',
                    'created_at' => now()->toISOString(),
                ];
            }
            
            if (str_contains($errorMessage, 'authentication expired') || str_contains($errorMessage, 'InvalidAuthenticationToken') || str_contains($errorMessage, 'ErrorInvalidGrant')) {
                return [
                    'success' => false,
                    'auth_required' => true,
                    'message' => 'Outlook authentication expired. Please reconnect your Outlook account.',
                    'type' => 'outlook_event',
                    'created_at' => now()->toISOString(),
                ];
            }

            // Return proper error instead of mock data
            return [
                'success' => false,
                'error' => 'Failed to create Outlook event',
                'message' => $errorMessage,
                'type' => 'outlook_event',
                'created_at' => now()->toISOString(),
            ];
        }
    }

    /**
     * Create a real Outlook calendar event via Microsoft Graph API.
     */
    public function createOutlookEvent(array $data, string $accessToken): array
    {
        $startDateTime = \Carbon\Carbon::parse($data['scheduled_at'])->toRfc3339String();
        $endDateTime = $this->calculateEndTime($data['scheduled_at'], $data['duration_minutes'] ?? 30);

        // STEP 1: Create Teams Meeting FIRST via OnlineMeetings API
        $teamsMeetingId = null;
        $teamsJoinUrl = null;

        Log::info('Creating Teams meeting via OnlineMeetings API', [
            'subject' => $data['title'],
            'start' => $startDateTime,
            'end' => $endDateTime
        ]);

        $teamsMeetingResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/me/onlineMeetings', [
            'startDateTime' => $startDateTime,
            'endDateTime' => $endDateTime,
            'subject' => $data['title'],
        ]);

        if ($teamsMeetingResponse->successful()) {
            $teamsMeeting = $teamsMeetingResponse->json();
            $teamsMeetingId = $teamsMeeting['id'] ?? null;
            $teamsJoinUrl = $teamsMeeting['joinWebUrl'] ?? null;

            Log::info('Teams meeting created successfully via OnlineMeetings API', [
                'meeting_id' => $teamsMeetingId,
                'join_url' => $teamsJoinUrl,
                'meeting_keys' => array_keys($teamsMeeting)
            ]);
        } else {
            $errorResponse = $teamsMeetingResponse->json();
            Log::warning('OnlineMeetings API failed (likely personal account), will try calendar event approach', [
                'status' => $teamsMeetingResponse->status(),
                'response' => $teamsMeetingResponse->body(),
                'error_code' => $errorResponse['error']['code'] ?? null,
                'error_message' => $errorResponse['error']['message'] ?? null
            ]);
            // Continue - will try calendar event with isOnlineMeeting for personal accounts
        }

        // STEP 2: Create Outlook Calendar Event with Teams meeting link
        // Note: We do NOT include attendees here to prevent Microsoft from sending automatic invitations.
        // Our system sends meeting notifications via SendMeetingNotificationJob using tenant's configured email.
        $eventDescription = $data['description'] ?? '';
        if ($teamsJoinUrl) {
            // Add Teams meeting link to the event description
            $eventDescription .= ($eventDescription ? "\n\n" : '') . "Join Teams meeting: " . $teamsJoinUrl;
        }

        $eventData = [
            'subject' => $data['title'],
            'body' => [
                'content' => $eventDescription,
                'contentType' => 'HTML'
            ],
            'start' => [
                'dateTime' => $startDateTime,
                'timeZone' => config('app.timezone', 'UTC'),
            ],
            'end' => [
                'dateTime' => $endDateTime,
                'timeZone' => config('app.timezone', 'UTC'),
            ],
            // Attendees removed to prevent Microsoft from sending automatic calendar invitations.
            // Meeting notifications are sent via SendMeetingNotificationJob using tenant's configured email.
            'location' => [
                'displayName' => 'Microsoft Teams'
            ],
        ];

        // For personal accounts: Try isOnlineMeeting without onlineMeetingProvider (fallback)
        if (!$teamsJoinUrl) {
            $eventData['isOnlineMeeting'] = true;
            // Don't set onlineMeetingProvider - let Microsoft decide (works for some personal accounts)
            Log::info('Creating event with isOnlineMeeting=true (no provider) for personal account fallback');
        }

        Log::info('Creating Outlook calendar event', [
            'request_data' => $eventData,
            'has_teams_link_from_api' => !empty($teamsJoinUrl),
            'access_token_length' => strlen($accessToken)
        ]);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/me/events', $eventData);

        if ($response->successful()) {
            $event = $response->json();
            
            // Check if Microsoft generated a meeting link in the response (for personal accounts)
            $eventJoinUrl = $event['onlineMeeting']['joinUrl'] ?? null;
            
            // Use Teams meeting link from OnlineMeetings API if available, otherwise use event's onlineMeeting
            $finalJoinUrl = $teamsJoinUrl ?? $eventJoinUrl;
            $finalMeetingId = $teamsMeetingId ?? $event['id'];
            
            Log::info('Outlook calendar event created successfully', [
                'event_id' => $event['id'],
                'teams_meeting_id_from_api' => $teamsMeetingId,
                'teams_join_url_from_api' => $teamsJoinUrl,
                'event_onlineMeeting_joinUrl' => $eventJoinUrl,
                'final_join_url' => $finalJoinUrl,
                'isOnlineMeeting' => $event['isOnlineMeeting'] ?? false,
                'onlineMeetingProvider' => $event['onlineMeetingProvider'] ?? null,
                'event_keys' => array_keys($event)
            ]);

            return [
                'success' => true,
                'meeting_id' => $finalMeetingId,
                'join_url' => $finalJoinUrl,
                'meeting_url' => $finalJoinUrl,
                'type' => 'outlook_event',
                'created_at' => now()->toISOString(),
                'meeting_code' => $this->extractMeetingCode($finalJoinUrl),
                'calendar_event_id' => $event['id'],
            ];
        } else {
            $errorResponse = $response->json();
            $errorCode = $errorResponse['error']['code'] ?? null;
            $errorMessage = $errorResponse['error']['message'] ?? $response->body();
            
            Log::error('Failed to create Outlook calendar event', [
                'status' => $response->status(),
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'response' => $response->body(),
                'request_data' => $eventData
            ]);

            // Handle specific Microsoft account errors
            if ($errorCode === 'ErrorAccountSuspend') {
                throw new \Exception('Your Microsoft account is suspended. Please check your email inbox and follow the instructions to verify your account, then reconnect your Outlook account.');
            }
            
            if ($errorCode === 'ErrorInvalidGrant' || $errorCode === 'InvalidAuthenticationToken') {
                throw new \Exception('Outlook authentication expired. Please reconnect your Outlook account.');
            }

            throw new \Exception('Failed to create Outlook calendar event: ' . $errorMessage);
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
    public function storeToken(array $tokenData, int $userId, int $tenantId): void
    {
        \App\Models\OutlookOAuthToken::updateOrCreate(
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
