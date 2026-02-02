<?php

namespace App\Models\Hr;

use App\Models\Traits\HasTenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'hr_leave_balances';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'leave_type_id',
        'balance',
        'accrued_this_year',
        'used_this_year',
        'last_accrual_date',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'accrued_this_year' => 'decimal:2',
        'used_this_year' => 'decimal:2',
        'last_accrual_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the tenant that owns the leave balance.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the employee that owns the leave balance.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Get the leave type.
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    /**
     * Check if balance is sufficient for requested days.
     */
    public function hasSufficientBalance(float $days): bool
    {
        return $this->balance >= $days;
    }
}

