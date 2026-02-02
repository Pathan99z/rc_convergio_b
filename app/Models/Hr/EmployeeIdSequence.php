<?php

namespace App\Models\Hr;

use App\Models\Traits\HasTenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeIdSequence extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'hr_employee_id_sequence';

    protected $fillable = [
        'tenant_id',
        'year',
        'last_sequence',
    ];

    protected $casts = [
        'year' => 'integer',
        'last_sequence' => 'integer',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the tenant that owns the sequence.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get or create sequence for tenant and year, then increment.
     */
    public static function getNextSequence(int $tenantId, int $year): int
    {
        $sequence = static::lockForUpdate()
            ->firstOrCreate(
                ['tenant_id' => $tenantId, 'year' => $year],
                ['last_sequence' => 0]
            );
        
        $sequence->increment('last_sequence');
        $sequence->refresh();
        
        return $sequence->last_sequence;
    }
}

