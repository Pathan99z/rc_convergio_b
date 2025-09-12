<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactSubscription extends Model
{
    protected $fillable = [
        'contact_id',
        'unsubscribed',
        'unsubscribed_at',
        'unsubscribed_via',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'unsubscribed' => 'boolean',
        'unsubscribed_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the contact that owns the subscription
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Check if contact is unsubscribed
     */
    public static function isUnsubscribed(int $contactId): bool
    {
        return self::where('contact_id', $contactId)
            ->where('unsubscribed', true)
            ->exists();
    }

    /**
     * Unsubscribe a contact
     */
    public static function unsubscribe(int $contactId, array $data = []): self
    {
        return self::updateOrCreate(
            ['contact_id' => $contactId],
            [
                'unsubscribed' => true,
                'unsubscribed_at' => now(),
                'unsubscribed_via' => $data['unsubscribed_via'] ?? 'email_link',
                'ip_address' => $data['ip_address'] ?? request()->ip(),
                'user_agent' => $data['user_agent'] ?? request()->userAgent(),
                'metadata' => $data['metadata'] ?? null,
            ]
        );
    }

    /**
     * Resubscribe a contact
     */
    public static function resubscribe(int $contactId): self
    {
        return self::updateOrCreate(
            ['contact_id' => $contactId],
            [
                'unsubscribed' => false,
                'unsubscribed_at' => null,
                'unsubscribed_via' => null,
            ]
        );
    }
}