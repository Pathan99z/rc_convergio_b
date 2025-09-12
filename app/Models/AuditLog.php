<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'campaign_id',
        'contact_id',
        'recipient_id',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get the user that performed the action
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the campaign related to this log
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Get the contact related to this log
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the recipient related to this log
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(CampaignRecipient::class);
    }

    /**
     * Log an audit event
     */
    public static function log(string $action, array $data = []): self
    {
        return self::create([
            'user_id' => $data['user_id'] ?? auth()->id(),
            'action' => $action,
            'campaign_id' => $data['campaign_id'] ?? null,
            'contact_id' => $data['contact_id'] ?? null,
            'recipient_id' => $data['recipient_id'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'ip_address' => $data['ip_address'] ?? request()->ip(),
            'user_agent' => $data['user_agent'] ?? request()->userAgent(),
        ]);
    }
}
