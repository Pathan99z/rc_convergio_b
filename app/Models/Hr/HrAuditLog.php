<?php

namespace App\Models\Hr;

use App\Models\Traits\HasTenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrAuditLog extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'hr_audit_logs';

    protected $fillable = [
        'tenant_id',
        'actor_id',
        'actor_role',
        'action',
        'entity',
        'entity_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the tenant that owns the audit log.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the user who performed the action.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Log an HR audit event.
     */
    public static function log(string $action, string $entity, ?int $entityId = null, array $oldValues = [], array $newValues = []): self
    {
        $user = auth()->user();
        
        return self::create([
            'tenant_id' => $user->tenant_id ?? $user->id,
            'actor_id' => $user->id,
            'actor_role' => $user->roles->first()->name ?? 'unknown',
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}

