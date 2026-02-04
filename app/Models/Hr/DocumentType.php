<?php

namespace App\Models\Hr;

use App\Models\Traits\HasTenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentType extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected $table = 'hr_document_types';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'description',
        'category',
        'is_mandatory',
        'employee_can_upload',
        'is_hr_only',
        'allowed_file_types',
        'max_file_size_mb',
        'target_departments',
        'target_designations',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
        'employee_can_upload' => 'boolean',
        'is_hr_only' => 'boolean',
        'is_active' => 'boolean',
        'allowed_file_types' => 'array',
        'target_departments' => 'array',
        'target_designations' => 'array',
        'max_file_size_mb' => 'integer',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the tenant that owns the document type.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the user who created the document type.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all employee documents of this type.
     */
    public function employeeDocuments(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class, 'document_type_id');
    }

    /**
     * Get all payslips of this type.
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class, 'document_type_id');
    }

    /**
     * Scope a query to only include active document types.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include mandatory document types.
     */
    public function scopeMandatory($query)
    {
        return $query->where('is_mandatory', true);
    }

    /**
     * Scope a query to filter by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope a query to only include document types that employees can upload.
     */
    public function scopeEmployeeUploadable($query)
    {
        return $query->where('employee_can_upload', true);
    }

    /**
     * Check if this document type is applicable to a specific employee.
     */
    public function isApplicableToEmployee(Employee $employee): bool
    {
        // Check department
        if ($this->target_departments !== null && !empty($this->target_departments)) {
            if (!in_array($employee->department_id, $this->target_departments)) {
                return false;
            }
        }

        // Check designation
        if ($this->target_designations !== null && !empty($this->target_designations)) {
            if (!in_array($employee->designation_id, $this->target_designations)) {
                return false;
            }
        }

        return true;
    }
}
