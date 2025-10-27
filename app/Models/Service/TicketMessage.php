<?php

namespace App\Models\Service;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TicketMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'author_id',
        'author_type',
        'body',
        'type',
        'direction',
    ];

    protected $casts = [
        'type' => 'string',
        'direction' => 'string',
    ];

    /**
     * Get the ticket that owns the message.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the author of the message (polymorphic).
     */
    public function author(): MorphTo
    {
        return $this->morphTo('author', 'author_type', 'author_id');
    }

    /**
     * Scope a query to only include public messages.
     */
    public function scopePublic($query)
    {
        return $query->where('type', 'public');
    }

    /**
     * Scope a query to only include internal messages.
     */
    public function scopeInternal($query)
    {
        return $query->where('type', 'internal');
    }

    /**
     * Scope a query to filter by direction.
     */
    public function scopeByDirection($query, $direction)
    {
        return $query->where('direction', $direction);
    }

    /**
     * Scope a query to filter by author.
     */
    public function scopeByAuthor($query, $authorId, $authorType = 'App\Models\User')
    {
        return $query->where('author_id', $authorId)
                    ->where('author_type', $authorType);
    }

    /**
     * Check if message is from customer.
     */
    public function isFromCustomer(): bool
    {
        return $this->direction === 'inbound';
    }

    /**
     * Check if message is from agent.
     */
    public function isFromAgent(): bool
    {
        return $this->direction === 'outbound';
    }

    /**
     * Check if message is public.
     */
    public function isPublic(): bool
    {
        return $this->type === 'public';
    }

    /**
     * Check if message is internal.
     */
    public function isInternal(): bool
    {
        return $this->type === 'internal';
    }
}
