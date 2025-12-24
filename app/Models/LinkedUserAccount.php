<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LinkedUserAccount extends Model
{
    protected $fillable = [
        'source_user_id',
        'target_user_id',
        'target_user_uid',
        'product_id',
        'integration_type',
        'status',
        'target_username',
        'metadata',
        'synced_at',
        'last_sync_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'synced_at' => 'datetime',
        'last_sync_at' => 'datetime',
    ];

    /**
     * Get the Convergio user that owns this linked account.
     */
    public function sourceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_user_id');
    }
}
