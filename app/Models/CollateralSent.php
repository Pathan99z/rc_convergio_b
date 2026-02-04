<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollateralSent extends Model
{
    use HasFactory, HasTenantScope;

    protected $fillable = [
        'contact_id',
        'collateral_id',
        'sent_by',
        'message',
        'sent_at',
        'tenant_id',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the contact that received the collateral.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the collateral that was sent.
     */
    public function collateral(): BelongsTo
    {
        return $this->belongsTo(Collateral::class);
    }

    /**
     * Get the user who sent the collateral.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /**
     * Get the tenant that owns this record.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }
}




