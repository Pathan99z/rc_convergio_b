<?php

namespace App\Http\Controllers\Api\Service;

use App\Http\Controllers\Controller;
use App\Models\Service\EmailIntegration;
use App\Services\Service\EmailToTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class EmailIntegrationController extends Controller
{
    public function __construct(
        private EmailToTicketService $emailToTicketService
    ) {}

    /**
     * Display a listing of email integrations.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id ?? $user->id;

            $integrations = EmailIntegration::forTenant($tenantId)
                ->with(['defaultAssignee', 'defaultTeam'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($integration) {
                    return [
                        'id' => $integration->id,
                        'email_address' => $integration->email_address,
                        'provider' => $integration->provider,
                        'is_active' => $integration->is_active,
                        'auto_create_tickets' => $integration->auto_create_tickets,
                        'default_priority' => $integration->default_priority,
                        'default_assignee' => $integration->defaultAssignee?->name,
                        'default_team' => $integration->defaultTeam?->name,
                        'last_sync_at' => $integration->last_sync_at?->toISOString(),
                        'tickets_created_count' => $integration->tickets_created_count,
                        'status' => $integration->status,
                        'display_name' => $integration->display_name,
                        'stats' => $integration->stats,
                        'created_at' => $integration->created_at->toISOString(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $integrations,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch email integrations', [
                'tenant_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch email integrations',
            ], 500);
        }
    }

    /**
     * Store a newly created email integration.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id ?? $user->id;

            $validator = Validator::make($request->all(), [
                'email_address' => 'required|email|max:255',
                'provider' => 'required|in:gmail,outlook,imap',
                'default_priority' => 'required|in:low,medium,high,urgent',
                'default_assignee_id' => 'nullable|exists:users,id',
                'default_team_id' => 'nullable|exists:teams,id',
                'team_assignment' => 'nullable|string', // Frontend field
                'subject_prefix' => 'nullable|string|max:100', // Frontend field
                'auto_create_tickets' => 'boolean',
                'notes' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $validatedData = $validator->validated();
            
            // Map frontend fields to backend fields
            $data = [
                'tenant_id' => $tenantId,
                'email_address' => $validatedData['email_address'],
                'provider' => $validatedData['provider'],
                'default_priority' => $validatedData['default_priority'],
                'default_assignee_id' => $validatedData['default_assignee_id'] ?? null,
                'default_team_id' => $validatedData['default_team_id'] ?? null,
                'auto_create_tickets' => $validatedData['auto_create_tickets'] ?? true,
                'notes' => $validatedData['notes'] ?? null,
                'is_active' => false, // Start as inactive until connected
            ];
            
            // Handle team_assignment mapping (frontend sends "support" but we need team ID)
            if (isset($validatedData['team_assignment']) && $validatedData['team_assignment'] === 'support') {
                // Find the support team for this tenant
                $supportTeam = \App\Models\Team::forTenant($tenantId)
                    ->where('name', 'like', '%support%')
                    ->first();
                if ($supportTeam) {
                    $data['default_team_id'] = $supportTeam->id;
                }
            }

            $integration = EmailIntegration::create($data);

            Log::info('Email integration created', [
                'integration_id' => $integration->id,
                'tenant_id' => $tenantId,
                'email_address' => $integration->email_address,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Email integration created successfully',
                'data' => [
                    'id' => $integration->id,
                    'email_address' => $integration->email_address,
                    'provider' => $integration->provider,
                    'status' => $integration->status,
                    'connection_url' => $this->generateConnectionUrl($integration),
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create email integration', [
                'tenant_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create email integration',
            ], 500);
        }
    }

    /**
     * Check if user can access the email integration.
     */
    private function checkAccess(EmailIntegration $emailIntegration): bool
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? $user->id;
        return $emailIntegration->tenant_id === $tenantId;
    }

    /**
     * Display the specified email integration.
     */
    public function show(EmailIntegration $emailIntegration): JsonResponse
    {
        try {
            if (!$this->checkAccess($emailIntegration)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this integration',
                ], 403);
            }

            $integration = $emailIntegration->load(['defaultAssignee', 'defaultTeam', 'tickets']);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $integration->id,
                    'email_address' => $integration->email_address,
                    'provider' => $integration->provider,
                    'is_active' => $integration->is_active,
                    'auto_create_tickets' => $integration->auto_create_tickets,
                    'default_priority' => $integration->default_priority,
                    'default_assignee' => $integration->defaultAssignee?->name,
                    'default_team' => $integration->defaultTeam?->name,
                    'last_sync_at' => $integration->last_sync_at?->toISOString(),
                    'tickets_created_count' => $integration->tickets_created_count,
                    'status' => $integration->status,
                    'display_name' => $integration->display_name,
                    'stats' => $integration->stats,
                    'notes' => $integration->notes,
                    'created_at' => $integration->created_at->toISOString(),
                    'updated_at' => $integration->updated_at->toISOString(),
                    'connection_url' => $this->generateConnectionUrl($integration),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch email integration', [
                'integration_id' => $emailIntegration->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch email integration',
            ], 500);
        }
    }

    /**
     * Update the specified email integration.
     */
    public function update(Request $request, EmailIntegration $emailIntegration): JsonResponse
    {
        try {
            if (!$this->checkAccess($emailIntegration)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this integration',
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'default_priority' => 'sometimes|in:low,medium,high,urgent',
                'default_assignee_id' => 'nullable|exists:users,id',
                'default_team_id' => 'nullable|exists:teams,id',
                'auto_create_tickets' => 'sometimes|boolean',
                'is_active' => 'sometimes|boolean',
                'notes' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $data = $validator->validated();
            $emailIntegration->update($data);

            Log::info('Email integration updated', [
                'integration_id' => $emailIntegration->id,
                'updated_fields' => array_keys($data),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Email integration updated successfully',
                'data' => [
                    'id' => $emailIntegration->id,
                    'status' => $emailIntegration->status,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update email integration', [
                'integration_id' => $emailIntegration->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update email integration',
            ], 500);
        }
    }

    /**
     * Remove the specified email integration.
     */
    public function destroy(EmailIntegration $emailIntegration): JsonResponse
    {
        try {
            if (!$this->checkAccess($emailIntegration)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this integration',
                ], 403);
            }

            $emailIntegration->delete();

            Log::info('Email integration deleted', [
                'integration_id' => $emailIntegration->id,
                'email_address' => $emailIntegration->email_address,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Email integration deleted successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete email integration', [
                'integration_id' => $emailIntegration->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete email integration',
            ], 500);
        }
    }

    /**
     * Test the email integration.
     */
    public function test(EmailIntegration $emailIntegration): JsonResponse
    {
        try {
            if (!$this->checkAccess($emailIntegration)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this integration',
                ], 403);
            }

            $result = $this->emailToTicketService->testIntegration($emailIntegration);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to test email integration', [
                'integration_id' => $emailIntegration->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to test email integration',
            ], 500);
        }
    }

    /**
     * Connect the email integration (OAuth flow).
     */
    public function connect(EmailIntegration $emailIntegration): JsonResponse
    {
        try {
            // Manual authorization check
            $user = Auth::user();
            $tenantId = $user->tenant_id ?? $user->id;
            
            if ($emailIntegration->tenant_id !== $tenantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this integration',
                ], 403);
            }

            // Generate OAuth URL based on provider
            $connectionUrl = $this->generateConnectionUrl($emailIntegration);

            return response()->json([
                'success' => true,
                'message' => 'Connection URL generated',
                'data' => [
                    'connection_url' => $connectionUrl,
                    'provider' => $emailIntegration->provider,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate connection URL', [
                'integration_id' => $emailIntegration->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate connection URL',
            ], 500);
        }
    }

    /**
     * Handle OAuth callback.
     */
    public function callback(Request $request, EmailIntegration $emailIntegration): JsonResponse
    {
        try {
            if (!$this->checkAccess($emailIntegration)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this integration',
                ], 403);
            }

            $code = $request->input('code');
            $state = $request->input('state');

            if (!$code) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authorization code not provided',
                ], 400);
            }

            // Exchange code for tokens (implementation depends on provider)
            $credentials = $this->exchangeCodeForTokens($code, $emailIntegration->provider);

            if (!$credentials) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to obtain access tokens',
                ], 400);
            }

            // Update integration with credentials
            $emailIntegration->update([
                'credentials' => $credentials,
                'is_active' => true,
                'last_sync_at' => now(),
            ]);

            Log::info('Email integration connected', [
                'integration_id' => $emailIntegration->id,
                'provider' => $emailIntegration->provider,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Email integration connected successfully',
                'data' => [
                    'id' => $emailIntegration->id,
                    'status' => $emailIntegration->status,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to handle OAuth callback', [
                'integration_id' => $emailIntegration->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to connect email integration',
            ], 500);
        }
    }

    /**
     * Get webhook URL for the integration.
     */
    public function webhookUrl(EmailIntegration $emailIntegration): JsonResponse
    {
        try {
            if (!$this->checkAccess($emailIntegration)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this integration',
                ], 403);
            }

            $webhookUrl = route('api.service.email.webhook');

            return response()->json([
                'success' => true,
                'data' => [
                    'webhook_url' => $webhookUrl,
                    'instructions' => $this->getWebhookInstructions($emailIntegration->provider),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get webhook URL',
            ], 500);
        }
    }

    /**
     * Generate OAuth connection URL.
     */
    private function generateConnectionUrl(EmailIntegration $integration): string
    {
        $baseUrl = config('app.url');
        $callbackUrl = route('api.service.email.integrations.callback', $integration->id);
        
        switch ($integration->provider) {
            case 'gmail':
                return "{$baseUrl}/oauth/gmail?callback=" . urlencode($callbackUrl);
            case 'outlook':
                return "{$baseUrl}/oauth/outlook?callback=" . urlencode($callbackUrl);
            default:
                return "{$baseUrl}/oauth/{$integration->provider}?callback=" . urlencode($callbackUrl);
        }
    }

    /**
     * Exchange authorization code for access tokens.
     */
    private function exchangeCodeForTokens(string $code, string $provider): ?array
    {
        // This would typically make API calls to the provider
        // For now, return mock credentials
        return [
            'access_token' => 'mock_access_token',
            'refresh_token' => 'mock_refresh_token',
            'expires_at' => now()->addHour()->timestamp,
        ];
    }

    /**
     * Get webhook setup instructions.
     */
    private function getWebhookInstructions(string $provider): array
    {
        $webhookUrl = route('api.service.email.webhook');
        
        switch ($provider) {
            case 'gmail':
                return [
                    'title' => 'Gmail API Setup',
                    'steps' => [
                        '1. Go to Google Cloud Console',
                        '2. Enable Gmail API',
                        '3. Create credentials',
                        "4. Set webhook URL to: {$webhookUrl}",
                        '5. Configure push notifications',
                    ],
                ];
            case 'outlook':
                return [
                    'title' => 'Microsoft Graph API Setup',
                    'steps' => [
                        '1. Go to Azure Portal',
                        '2. Register your application',
                        '3. Grant Mail.Read permissions',
                        "4. Set webhook URL to: {$webhookUrl}",
                        '5. Subscribe to change notifications',
                    ],
                ];
            default:
                return [
                    'title' => 'Generic Email Setup',
                    'steps' => [
                        "1. Configure your email service",
                        "2. Set webhook URL to: {$webhookUrl}",
                        '3. Test the connection',
                    ],
                ];
        }
    }
}