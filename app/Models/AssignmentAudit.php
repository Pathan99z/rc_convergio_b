<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'record_type',
        'record_id',
        'assigned_to',
        'rule_id',
        'assignment_type',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    /**
     * Get the tenant that owns the audit record.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the user who was assigned.
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the rule that was used for assignment.
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(AssignmentRule::class, 'rule_id');
    }

    /**
     * Scope a query to only include audits for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include audits for a specific record.
     */
    public function scopeForRecord($query, $recordType, $recordId)
    {
        return $query->where('record_type', $recordType)
                    ->where('record_id', $recordId);
    }

    /**
     * Scope a query to only include audits for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    /**
     * Scope a query to only include audits for a specific rule.
     */
    public function scopeForRule($query, $ruleId)
    {
        return $query->where('rule_id', $ruleId);
    }

    /**
     * Create an audit record for an assignment.
     */
    public static function createAudit(
        int $tenantId,
        string $recordType,
        int $recordId,
        ?int $assignedTo,
        ?int $ruleId = null,
        string $assignmentType = 'default',
        array $details = []
    ): self {
        return static::create([
            'tenant_id' => $tenantId,
            'record_type' => $recordType,
            'record_id' => $recordId,
            'assigned_to' => $assignedTo,
            'rule_id' => $ruleId,
            'assignment_type' => $assignmentType,
            'details' => $details,
        ]);
    }
}
