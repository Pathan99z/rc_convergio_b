<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected $fillable = [
        'quote_number',
        'deal_id',
        'contact_id',
        'template_id',
        'subtotal',
        'tax',
        'discount',
        'total',
        'currency',
        'status',
        'valid_until',
        'pdf_path',
        'uuid',
        'accepted_at',
        'rejected_at',
        'is_primary',
        'quote_type',
        'tenant_id',
        'created_by',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'valid_until' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'is_primary' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
        
        // Generate UUID when creating a new quote
        static::creating(function ($quote) {
            if (empty($quote->uuid)) {
                $quote->uuid = \Illuminate\Support\Str::uuid()->toString();
            }
        });
    }

    /**
     * Get the deal that owns the quote.
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /**
     * Get the contact associated with the quote.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the items for the quote.
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('sort_order');
    }

    /**
     * Get the tenant that owns the quote.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the user who created the quote.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the template used for this quote.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(QuoteTemplate::class);
    }

    /**
     * Scope a query to filter by tenant.
     */
    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to filter by deal.
     */
    public function scopeByDeal($query, $dealId)
    {
        return $query->where('deal_id', $dealId);
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to filter by creator.
     */
    public function scopeByCreator($query, $creatorId)
    {
        return $query->where('created_by', $creatorId);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeByDateRange($query, $fromDate, $toDate)
    {
        return $query->whereBetween('created_at', [$fromDate, $toDate]);
    }

    /**
     * Scope a query to filter by quote type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('quote_type', $type);
    }

    /**
     * Scope a query to get primary quotes.
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope a query to get follow-up quotes.
     */
    public function scopeFollowUp($query)
    {
        return $query->where('is_primary', false);
    }

    /**
     * Check if the quote is expired.
     */
    public function isExpired(): bool
    {
        return $this->valid_until && $this->valid_until->isPast();
    }

    /**
     * Check if the quote can be modified.
     */
    public function canBeModified(): bool
    {
        return in_array($this->status, ['draft']);
    }

    /**
     * Check if the quote can be sent.
     */
    public function canBeSent(): bool
    {
        return in_array($this->status, ['draft']) && $this->items()->count() > 0;
    }

    /**
     * Check if the quote can be accepted.
     */
    public function canBeAccepted(): bool
    {
        return in_array($this->status, ['sent']);
    }

    /**
     * Check if the quote can be rejected.
     */
    public function canBeRejected(): bool
    {
        return in_array($this->status, ['sent']);
    }

    /**
     * Check if this is the primary quote for the deal.
     */
    public function isPrimary(): bool
    {
        return $this->is_primary;
    }

    /**
     * Check if this is a follow-up quote.
     */
    public function isFollowUp(): bool
    {
        return !$this->is_primary;
    }

    /**
     * Get the quote type display name.
     */
    public function getQuoteTypeDisplayAttribute(): string
    {
        return match($this->quote_type) {
            'primary' => 'Primary',
            'follow-up' => 'Follow-up',
            'renewal' => 'Renewal',
            'amendment' => 'Amendment',
            'alternative' => 'Alternative',
            default => 'Primary'
        };
    }

    /**
     * Mark this quote as the primary quote for the deal.
     */
    public function markAsPrimary(): void
    {
        // First, unmark any existing primary quotes for this deal
        static::where('deal_id', $this->deal_id)
            ->where('id', '!=', $this->id)
            ->update(['is_primary' => false]);

        // Mark this quote as primary
        $this->update(['is_primary' => true, 'quote_type' => 'primary']);
    }

    /**
     * Mark this quote as a follow-up quote.
     */
    public function markAsFollowUp(string $type = 'follow-up'): void
    {
        $this->update([
            'is_primary' => false,
            'quote_type' => $type
        ]);
    }
}