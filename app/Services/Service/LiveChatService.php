<?php

namespace App\Services\Service;

use App\Models\Service\LiveChatConversation;
use App\Models\Service\LiveChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LiveChatService
{
    /**
     * Start a new conversation.
     */
    public function startConversation(array $data, int $tenantId): LiveChatConversation
    {
        return DB::transaction(function () use ($data, $tenantId) {
            $sessionId = $data['session_id'] ?? Str::uuid()->toString();
            
            $conversation = LiveChatConversation::create([
                'tenant_id' => $tenantId,
                'session_id' => $sessionId,
                'customer_name' => $data['customer_name'] ?? null,
                'customer_email' => $data['customer_email'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'status' => 'active',
                'last_message_at' => now(),
            ]);

            Log::info('Live chat conversation started', [
                'conversation_id' => $conversation->id,
                'tenant_id' => $tenantId,
                'session_id' => $sessionId,
            ]);

            return $conversation;
        });
    }

    /**
     * Send a message in a conversation.
     */
    public function sendMessage(int $conversationId, array $data): LiveChatMessage
    {
        return DB::transaction(function () use ($conversationId, $data) {
            $conversation = LiveChatConversation::findOrFail($conversationId);
            
            $message = LiveChatMessage::create([
                'conversation_id' => $conversationId,
                'sender_type' => $data['sender_type'], // 'customer' or 'agent'
                'sender_id' => $data['sender_id'] ?? null, // Agent ID if sender_type is 'agent'
                'message' => $data['message'],
                'message_type' => $data['message_type'] ?? 'text',
                'metadata' => $data['metadata'] ?? null,
            ]);

            // Update conversation
            $conversation->incrementMessageCount();

            Log::info('Live chat message sent', [
                'message_id' => $message->id,
                'conversation_id' => $conversationId,
                'sender_type' => $data['sender_type'],
            ]);

            return $message;
        });
    }

    /**
     * Get conversation with messages.
     */
    public function getConversation(int $conversationId): ?LiveChatConversation
    {
        return LiveChatConversation::with(['messages' => function ($query) {
            $query->orderBy('created_at', 'asc');
        }, 'assignedAgent'])
        ->find($conversationId);
    }

    /**
     * Get active conversations for a tenant.
     */
    public function getActiveConversations(int $tenantId): \Illuminate\Database\Eloquent\Collection
    {
        return LiveChatConversation::forTenant($tenantId)
            ->active()
            ->with(['latestMessage', 'assignedAgent'])
            ->orderBy('last_message_at', 'desc')
            ->get();
    }

    /**
     * Get conversations assigned to an agent.
     */
    public function getAgentConversations(int $agentId): \Illuminate\Database\Eloquent\Collection
    {
        return LiveChatConversation::assignedTo($agentId)
            ->active()
            ->with(['latestMessage', 'assignedAgent'])
            ->orderBy('last_message_at', 'desc')
            ->get();
    }

    /**
     * Assign conversation to an agent.
     */
    public function assignConversation(int $conversationId, int $agentId): bool
    {
        $conversation = LiveChatConversation::findOrFail($conversationId);
        $conversation->assignToAgent($agentId);

        Log::info('Live chat conversation assigned', [
            'conversation_id' => $conversationId,
            'agent_id' => $agentId,
        ]);

        return true;
    }

    /**
     * Close a conversation.
     */
    public function closeConversation(int $conversationId): bool
    {
        $conversation = LiveChatConversation::findOrFail($conversationId);
        $conversation->close();

        Log::info('Live chat conversation closed', [
            'conversation_id' => $conversationId,
        ]);

        return true;
    }

    /**
     * Mark messages as read.
     */
    public function markMessagesAsRead(int $conversationId, string $senderType): int
    {
        return LiveChatMessage::where('conversation_id', $conversationId)
            ->where('sender_type', '!=', $senderType) // Mark messages from other party as read
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * Get unread message count for an agent.
     */
    public function getUnreadCount(int $agentId): int
    {
        return LiveChatMessage::whereHas('conversation', function ($query) use ($agentId) {
            $query->assignedTo($agentId);
        })
        ->where('sender_type', 'customer')
        ->where('is_read', false)
        ->count();
    }

    /**
     * Get conversation statistics for a tenant.
     */
    public function getConversationStats(int $tenantId): array
    {
        $total = LiveChatConversation::forTenant($tenantId)->count();
        $active = LiveChatConversation::forTenant($tenantId)->active()->count();
        $closed = LiveChatConversation::forTenant($tenantId)->where('status', 'closed')->count();
        $unassigned = LiveChatConversation::forTenant($tenantId)->unassigned()->active()->count();

        return [
            'total_conversations' => $total,
            'active_conversations' => $active,
            'closed_conversations' => $closed,
            'unassigned_conversations' => $unassigned,
        ];
    }

    /**
     * Find or create conversation by session ID.
     */
    public function findOrCreateConversation(string $sessionId, int $tenantId, array $customerData = []): LiveChatConversation
    {
        $conversation = LiveChatConversation::forTenant($tenantId)
            ->where('session_id', $sessionId)
            ->active()
            ->first();

        if (!$conversation) {
            $conversation = $this->startConversation([
                'session_id' => $sessionId,
                ...$customerData,
            ], $tenantId);
        }

        return $conversation;
    }

    /**
     * Get available agents for assignment.
     */
    public function getAvailableAgents(int $tenantId): \Illuminate\Database\Eloquent\Collection
    {
        // Get all users for the tenant - they can all be agents
        // In a real system, you might want to check permissions or status
        return User::where('tenant_id', $tenantId)
            ->where('status', 'active') // Only active users
            ->get();
    }
}
