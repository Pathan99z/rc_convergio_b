<?php

namespace App\Models\Hr;

use App\Models\Traits\HasTenantScope;
use App\Models\User;
use App\Models\Team;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class Employee extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected $table = 'hr_employees';

    protected $fillable = [
        'tenant_id',
        'team_id',
        'employee_id',
        'user_id',
        'profile_picture_id',
        'first_name',
        'last_name',
        'preferred_name',
        'date_of_birth',
        'gender',
        'nationality',
        'marital_status',
        'id_number',
        'passport_number',
        'work_email',
        'personal_email',
        'phone_number',
        'work_phone',
        'office_address',
        'job_title', // Keep for backward compatibility
        'department', // Keep for backward compatibility
        'department_id',
        'designation_id',
        'employment_type',
        'employment_status',
        'start_date',
        'end_date',
        'work_schedule',
        'probation_end_date',
        'contract_end_date',
        'manager_id',
        'salary',
        'bank_account',
        'address',
        'emergency_contact',
        'created_by',
        'archived_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'date_of_birth' => 'date',
        'probation_end_date' => 'date',
        'contract_end_date' => 'date',
        'archived_at' => 'datetime',
        'address' => 'array',
        'emergency_contact' => 'array',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the tenant that owns the employee.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the team that owns the employee.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    /**
     * Get the user account linked to this employee.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the manager of this employee.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    /**
     * Get direct reports (employees managed by this employee).
     */
    public function directReports(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    /**
     * Get leave balances for this employee.
     */
    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class, 'employee_id');
    }

    /**
     * Get leave requests for this employee.
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'employee_id');
    }

    /**
     * Get payslips for this employee.
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class, 'employee_id');
    }

    /**
     * Get documents for this employee.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class, 'employee_id');
    }

    /**
     * Get performance notes for this employee.
     */
    public function performanceNotes(): HasMany
    {
        return $this->hasMany(PerformanceNote::class, 'employee_id');
    }

    /**
     * Get onboarding checklist items for this employee.
     */
    public function onboardingChecklists(): HasMany
    {
        return $this->hasMany(OnboardingChecklist::class, 'employee_id');
    }

    /**
     * Get onboarding tasks for this employee.
     */
    public function onboardingTasks(): HasMany
    {
        return $this->hasMany(OnboardingTask::class, 'employee_id');
    }

    /**
     * Get the creator of this employee record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the department of this employee.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Get the designation of this employee.
     */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'designation_id');
    }

    /**
     * Get the profile picture of this employee.
     */
    public function profilePicture(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'profile_picture_id');
    }

    /**
     * Get full name attribute.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Get decrypted salary.
     */
    public function getDecryptedSalaryAttribute(): ?string
    {
        return $this->salary ? Crypt::decryptString($this->salary) : null;
    }

    /**
     * Set encrypted salary.
     */
    public function setSalaryAttribute($value): void
    {
        $this->attributes['salary'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Get decrypted bank account.
     */
    public function getDecryptedBankAccountAttribute(): ?string
    {
        return $this->bank_account ? Crypt::decryptString($this->bank_account) : null;
    }

    /**
     * Set encrypted bank account.
     */
    public function setBankAccountAttribute($value): void
    {
        $this->attributes['bank_account'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Scope a query to only include active employees.
     */
    public function scopeActive($query)
    {
        return $query->where('employment_status', 'active');
    }

    /**
     * Scope a query to only include onboarding employees.
     */
    public function scopeOnboarding($query)
    {
        return $query->where('employment_status', 'onboarding');
    }

    /**
     * Scope a query to only include offboarded employees.
     */
    public function scopeOffboarded($query)
    {
        return $query->where('employment_status', 'offboarded');
    }

    /**
     * Scope a query to only include archived employees.
     */
    public function scopeArchived($query)
    {
        return $query->whereNotNull('archived_at');
    }

    /**
     * Check if employee is active.
     */
    public function isActive(): bool
    {
        return $this->employment_status === 'active';
    }

    /**
     * Check if employee is archived.
     */
    public function isArchived(): bool
    {
        return !is_null($this->archived_at);
    }
}

