<?php

namespace App\Models\Hr;

use App\Models\Traits\HasTenantScope;
use App\Models\User;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'hr_payslips';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'payslip_number',
        'pay_period_start',
        'pay_period_end',
        'document_id',
        'document_type_id',
        'uploaded_by',
        'uploaded_at',
    ];

    protected $casts = [
        'pay_period_start' => 'date',
        'pay_period_end' => 'date',
        'uploaded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the tenant that owns the payslip.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the employee that owns the payslip.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Get the document (PDF file).
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
     * Get the user who uploaded the payslip.
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

