<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Cache;

class Deal extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected $fillable = [
        'title',
        'description',
        'value',
        'currency',
        'status',
        'expected_close_date',
        'closed_date',
        'close_reason',
        'probability',
        'tags',
        'pipeline_id',
        'stage_id',
        'owner_id',
        'contact_id',
        'company_id',
        'tenant_id',
        'team_id',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'probability' => 'integer',
        'expected_close_date' => 'datetime',
        'closed_date' => 'datetime',
        'tags' => 'array',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the pipeline that owns the deal.
     */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /**
     * Get the stage that owns the deal.
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    /**
     * Get the owner of the deal.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the contact associated with the deal.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the company associated with the deal.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the tenant that owns the deal.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the team that owns the deal.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get all activities for the deal.
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    /**
     * Get all stage movement history for the deal.
     */
    public function stageHistory(): HasMany
    {
        return $this->hasMany(DealStageHistory::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get the latest stage movement for the deal.
     */
    public function latestStageMovement(): HasOne
    {
        return $this->hasOne(DealStageHistory::class)->latestOfMany();
    }

    /**
     * Get all quotes for the deal.
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /**
     * Get the primary accepted quote for the deal.
     */
    public function primaryQuote(): HasMany
    {
        return $this->hasMany(Quote::class)->where('is_primary', true);
    }

    /**
     * Get all accepted quotes for the deal.
     */
    public function acceptedQuotes(): HasMany
    {
        return $this->hasMany(Quote::class)->where('status', 'accepted');
    }

    /**
     * Get all follow-up quotes for the deal.
     */
    public function followUpQuotes(): HasMany
    {
        return $this->hasMany(Quote::class)->where('is_primary', false);
    }

    /**
     * Scope a query to only include deals for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to filter by pipeline.
     */
    public function scopeByPipeline($query, $pipelineId)
    {
        return $query->where('pipeline_id', $pipelineId);
    }

    /**
     * Scope a query to filter by stage.
     */
    public function scopeByStage($query, $stageId)
    {
        return $query->where('stage_id', $stageId);
    }

    /**
     * Scope a query to filter by owner.
     */
    public function scopeByOwner($query, $ownerId)
    {
        return $query->where('owner_id', $ownerId);
    }

    /**
     * Scope a query to filter by contact.
     */
    public function scopeByContact($query, $contactId)
    {
        return $query->where('contact_id', $contactId);
    }

    /**
     * Scope a query to filter by company.
     */
    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeByDateRange($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Check if the deal has any accepted quotes.
     */
    public function hasAcceptedQuotes(): bool
    {
        return $this->acceptedQuotes()->exists();
    }

    /**
     * Get the primary accepted quote for this deal.
     */
    public function getPrimaryAcceptedQuote(): ?Quote
    {
        return $this->primaryQuote()->where('status', 'accepted')->first();
    }

    /**
     * Get the total revenue from all accepted quotes.
     */
    public function getTotalAcceptedRevenue(): float
    {
        return $this->acceptedQuotes()->sum('total');
    }

    /**
     * Get the count of accepted quotes.
     */
    public function getAcceptedQuotesCount(): int
    {
        return $this->acceptedQuotes()->count();
    }

    /**
     * Get the count of follow-up quotes.
     */
    public function getFollowUpQuotesCount(): int
    {
        return $this->followUpQuotes()->count();
    }

    /**
     * Check if this deal has a primary accepted quote.
     */
    public function hasPrimaryAcceptedQuote(): bool
    {
        return $this->getPrimaryAcceptedQuote() !== null;
    }

    /**
     * Get all quote types for this deal.
     */
    public function getQuoteTypes(): array
    {
        return $this->quotes()
            ->select('quote_type')
            ->distinct()
            ->pluck('quote_type')
            ->toArray();
    }

    /**
     * Get revenue breakdown by quote type.
     */
    public function getRevenueByQuoteType(): array
    {
        return $this->acceptedQuotes()
            ->selectRaw('quote_type, SUM(total) as total_revenue')
            ->groupBy('quote_type')
            ->pluck('total_revenue', 'quote_type')
            ->toArray();
    }

    /**
     * Get cached deal with relationships
     */
    public static function getCached($id)
    {
        return Cache::remember("deal_{$id}", 3600, function() use ($id) {
            return static::with(['pipeline', 'stage', 'owner', 'contact', 'company'])->find($id);
        });
    }

    /**
     * Get cached deals for tenant
     */
    public static function getCachedForTenant($tenantId, $limit = 50)
    {
        return Cache::remember("deals_tenant_{$tenantId}_{$limit}", 1800, function() use ($tenantId, $limit) {
            return static::where('tenant_id', $tenantId)
                ->with(['pipeline', 'stage', 'owner', 'contact', 'company'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();
        });
    }
}