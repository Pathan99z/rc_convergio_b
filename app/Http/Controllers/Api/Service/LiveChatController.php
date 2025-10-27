<?php

namespace App\Http\Controllers\Api\Service;

use App\Http\Controllers\Controller;
use App\Models\Service\LiveChatConversation;
use App\Services\Service\LiveChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LiveChatController extends Controller
{
    public function __construct(
        private LiveChatService $liveChatService
    ) {
        // Note: Public endpoints don't need auth middleware
        // Only agent endpoints need authentication
    }

    /**
     * Start a new conversation (public endpoint for customers).
     */
    public function startConversation(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'session_id' => 'required|string|max:255',
                'customer_name' => 'nullable|string|max:255',
                'customer_email' => 'nullable|email|max:255',
                'customer_phone' => 'nullable|string|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Get tenant ID from request (for public access)
            $tenantId = $this->getTenantIdFromRequest($request);
            
            if (!$tenantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant ID is required for live chat access',
                ], 400);
            }

            $conversation = $this->liveChatService->findOrCreateConversation(
                $request->session_id,
                $tenantId,
                $request->only(['customer_name', 'customer_email', 'customer_phone'])
            );

            return response()->json([
                'success' => true,
                'message' => 'Conversation started successfully',
                'data' => [
                    'conversation_id' => $conversation->id,
                    'session_id' => $conversation->session_id,
                    'status' => $conversation->status,
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to start live chat conversation', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to start conversation',
            ], 500);
        }
    }

    /**
     * Send a message in a conversation.
     */
    public function sendMessage(Request $request, int $conversationId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'message' => 'required|string|max:2000',
                'sender_type' => 'required|in:customer,agent',
                'sender_id' => 'nullable|exists:users,id',
                'message_type' => 'nullable|in:text,image,file,system',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $message = $this->liveChatService->sendMessage($conversationId, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully',
                'data' => [
                    'message_id' => $message->id,
                    'conversation_id' => $message->conversation_id,
                    'message' => $message->message,
                    'sender_type' => $message->sender_type,
                    'created_at' => $message->created_at,
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to send live chat message', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send message',
            ], 500);
        }
    }

    /**
     * Get conversation with messages.
     */
    public function getConversation(int $conversationId): JsonResponse
    {
        try {
            $conversation = $this->liveChatService->getConversation($conversationId);

            if (!$conversation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversation not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'conversation' => [
                        'id' => $conversation->id,
                        'session_id' => $conversation->session_id,
                        'customer_name' => $conversation->customer_name,
                        'customer_email' => $conversation->customer_email,
                        'status' => $conversation->status,
                        'assigned_agent' => $conversation->assignedAgent ? [
                            'id' => $conversation->assignedAgent->id,
                            'name' => $conversation->assignedAgent->name,
                        ] : null,
                        'created_at' => $conversation->created_at,
                    ],
                    'messages' => $conversation->messages->map(function ($message) {
                        return [
                            'id' => $message->id,
                            'message' => $message->message,
                            'sender_type' => $message->sender_type,
                            'sender_name' => $message->sender_display_name,
                            'message_type' => $message->message_type,
                            'is_read' => $message->is_read,
                            'created_at' => $message->created_at,
                            'formatted_time' => $message->formatted_time,
                        ];
                    }),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get live chat conversation', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get conversation',
            ], 500);
        }
    }

    /**
     * Get active conversations for authenticated user's tenant.
     */
    public function getActiveConversations(): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id ?? $user->id;

            $conversations = $this->liveChatService->getActiveConversations($tenantId);

            return response()->json([
                'success' => true,
                'data' => $conversations->map(function ($conversation) {
                    return [
                        'id' => $conversation->id,
                        'session_id' => $conversation->session_id,
                        'customer_name' => $conversation->customer_display_name,
                        'customer_email' => $conversation->customer_email,
                        'status' => $conversation->status,
                        'assigned_agent' => $conversation->assignedAgent ? [
                            'id' => $conversation->assignedAgent->id,
                            'name' => $conversation->assignedAgent->name,
                        ] : null,
                        'message_count' => $conversation->message_count,
                        'last_message_at' => $conversation->last_message_at,
                        'latest_message' => $conversation->latestMessage ? [
                            'message' => $conversation->latestMessage->message,
                            'sender_type' => $conversation->latestMessage->sender_type,
                            'created_at' => $conversation->latestMessage->created_at,
                        ] : null,
                    ];
                }),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get active live chat conversations', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get conversations',
            ], 500);
        }
    }

    /**
     * Assign conversation to an agent.
     */
    public function assignConversation(Request $request, int $conversationId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'agent_id' => 'required|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $this->liveChatService->assignConversation($conversationId, $request->agent_id);

            return response()->json([
                'success' => true,
                'message' => 'Conversation assigned successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to assign live chat conversation', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign conversation',
            ], 500);
        }
    }

    /**
     * Close a conversation.
     */
    public function closeConversation(int $conversationId): JsonResponse
    {
        try {
            $this->liveChatService->closeConversation($conversationId);

            return response()->json([
                'success' => true,
                'message' => 'Conversation closed successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to close live chat conversation', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to close conversation',
            ], 500);
        }
    }

    /**
     * Mark messages as read.
     */
    public function markAsRead(int $conversationId): JsonResponse
    {
        try {
            $user = Auth::user();
            $senderType = $user->role === 'agent' || $user->role === 'admin' ? 'agent' : 'customer';
            
            $count = $this->liveChatService->markMessagesAsRead($conversationId, $senderType);

            return response()->json([
                'success' => true,
                'message' => 'Messages marked as read',
                'data' => [
                    'marked_count' => $count,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to mark live chat messages as read', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark messages as read',
            ], 500);
        }
    }

    /**
     * Get conversation statistics.
     */
    public function getStats(): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id ?? $user->id;

            $stats = $this->liveChatService->getConversationStats($tenantId);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get live chat statistics', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics',
            ], 500);
        }
    }

    /**
     * Get available agents for assignment.
     */
    public function getAvailableAgents(): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id ?? $user->id;

            $agents = $this->liveChatService->getAvailableAgents($tenantId);

            return response()->json([
                'success' => true,
                'data' => $agents->map(function ($agent) {
                    return [
                        'id' => $agent->id,
                        'name' => $agent->name,
                        'email' => $agent->email,
                        'role' => $agent->role,
                    ];
                }),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get available agents', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get agents',
            ], 500);
        }
    }

    /**
     * Extract tenant ID from request (for public access).
     */
    private function getTenantIdFromRequest(Request $request): ?int
    {
        // Try to get from authenticated user first
        if ($request->user()) {
            return $request->user()->tenant_id ?? $request->user()->id;
        }

        // Try to get from request parameters
        if ($request->has('tenant_id')) {
            return (int) $request->input('tenant_id');
        }

        // Try to get from referer URL
        $referer = $request->header('referer');
        if ($referer && preg_match('/[?&]tenant=(\d+)/', $referer, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}