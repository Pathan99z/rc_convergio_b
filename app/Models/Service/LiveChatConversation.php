<?php

namespace App\Models\Service;

use App\Models\Traits\HasTenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveChatConversation extends Model
{
    use HasFactory, HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'session_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'status',
        'assigned_agent_id',
        'last_message_at',
        'message_count',
        'notes',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'message_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the tenant that owns the conversation.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the assigned agent for the conversation.
     */
    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    /**
     * Get the messages for the conversation.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(LiveChatMessage::class, 'conversation_id');
    }

    /**
     * Get the latest message for the conversation.
     */
    public function latestMessage(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(LiveChatMessage::class, 'conversation_id')->latest();
    }

    /**
     * Scope a query to only include active conversations.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include conversations assigned to a specific agent.
     */
    public function scopeAssignedTo($query, int $agentId)
    {
        return $query->where('assigned_agent_id', $agentId);
    }

    /**
     * Scope a query to only include unassigned conversations.
     */
    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_agent_id');
    }

    /**
     * Check if the conversation is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the conversation is assigned to an agent.
     */
    public function isAssigned(): bool
    {
        return !is_null($this->assigned_agent_id);
    }

    /**
     * Get the customer display name.
     */
    public function getCustomerDisplayNameAttribute(): string
    {
        return $this->customer_name ?: 'Anonymous Customer';
    }

    /**
     * Increment message count and update last message time.
     */
    public function incrementMessageCount(): void
    {
        $this->increment('message_count');
        $this->update(['last_message_at' => now()]);
    }

    /**
     * Mark conversation as closed.
     */
    public function close(): void
    {
        $this->update(['status' => 'closed']);
    }

    /**
     * Assign conversation to an agent.
     */
    public function assignToAgent(int $agentId): void
    {
        $this->update(['assigned_agent_id' => $agentId]);
    }
}