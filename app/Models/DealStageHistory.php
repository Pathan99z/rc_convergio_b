<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Traits\HasTenantScope;

class DealStageHistory extends Model
{
    use HasFactory, HasTenantScope;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'deal_stage_history';

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    protected $fillable = [
        'deal_id',
        'from_stage_id',
        'to_stage_id',
        'reason',
        'moved_by',
        'tenant_id',
    ];

    protected $casts = [
        'deal_id' => 'integer',
        'from_stage_id' => 'integer',
        'to_stage_id' => 'integer',
        'moved_by' => 'integer',
        'tenant_id' => 'integer',
    ];

    /**
     * Get the deal that owns the stage history.
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /**
     * Get the stage the deal was moved from.
     */
    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(Stage::class, 'from_stage_id');
    }

    /**
     * Get the stage the deal was moved to.
     */
    public function toStage(): BelongsTo
    {
        return $this->belongsTo(Stage::class, 'to_stage_id');
    }

    /**
     * Get the user who moved the deal.
     */
    public function movedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moved_by');
    }

    /**
     * Scope a query to only include history for a specific deal.
     */
    public function scopeForDeal($query, $dealId)
    {
        return $query->where('deal_id', $dealId)->orderBy('created_at', 'desc');
    }

    /**
     * Scope a query to get recent movements.
     */
    public function scopeRecent($query, $limit = 10)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    /**
     * Scope a query to only include history for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
