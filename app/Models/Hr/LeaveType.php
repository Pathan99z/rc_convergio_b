<?php

namespace App\Models\Hr;

use App\Models\Traits\HasTenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'hr_leave_types';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'accrues_monthly',
        'max_balance',
        'carry_forward',
        'requires_approval',
        'is_active',
    ];

    protected $casts = [
        'accrues_monthly' => 'boolean',
        'max_balance' => 'decimal:2',
        'carry_forward' => 'boolean',
        'requires_approval' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the tenant that owns the leave type.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get leave balances for this leave type.
     */
    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class, 'leave_type_id');
    }

    /**
     * Get leave requests for this leave type.
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'leave_type_id');
    }

    /**
     * Scope a query to only include active leave types.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

