<?php

namespace App\Models\Hr;

use App\Models\Traits\HasTenantScope;
use App\Models\User;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDocument extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'hr_employee_documents';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'document_id',
        'category',
        'document_type_id',
        'is_hr_only',
        'verification_status',
        'rejection_reason',
        'verified_by',
        'verified_at',
        'rejected_by',
        'rejected_at',
        'created_by',
    ];

    protected $casts = [
        'is_hr_only' => 'boolean',
        'verified_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the tenant that owns the employee document.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the employee that owns the document.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Get the document.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    /**
     * Get the document type.
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    /**
     * Get the user who created the document link.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who verified the document.
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Get the user who rejected the document.
     */
    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Scope a query to only include HR-only documents.
     */
    public function scopeHrOnly($query)
    {
        return $query->where('is_hr_only', true);
    }

    /**
     * Scope a query to filter by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope a query to only include pending verification documents.
     */
    public function scopePendingVerification($query)
    {
        return $query->where('verification_status', 'pending');
    }

    /**
     * Scope a query to only include verified documents.
     */
    public function scopeVerified($query)
    {
        return $query->where('verification_status', 'verified');
    }

    /**
     * Scope a query to only include rejected documents.
     */
    public function scopeRejected($query)
    {
        return $query->where('verification_status', 'rejected');
    }

    /**
     * Check if the document is pending verification.
     */
    public function isPendingVerification(): bool
    {
        return $this->verification_status === 'pending';
    }

    /**
     * Check if the document is verified.
     */
    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    /**
     * Check if the document is rejected.
     */
    public function isRejected(): bool
    {
        return $this->verification_status === 'rejected';
    }
}

