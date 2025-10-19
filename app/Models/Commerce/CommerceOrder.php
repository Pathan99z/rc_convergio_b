<?php

namespace App\Models\Commerce;

use App\Models\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommerceOrder extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected $fillable = [
        'order_number',
        'deal_id',
        'quote_id',
        'contact_id',
        'customer_snapshot',
        'subtotal',
        'tax',
        'discount',
        'total_amount',
        'currency',
        'status',
        'payment_method',
        'payment_reference',
        'tenant_id',
        'team_id',
        'owner_id',
        'created_by',
    ];

    protected $casts = [
        'customer_snapshot' => 'array',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the order items for the order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(CommerceOrderItem::class, 'order_id');
    }

    /**
     * Get the deal that owns the order.
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Deal::class);
    }

    /**
     * Get the quote that owns the order.
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Quote::class);
    }

    /**
     * Get the contact that owns the order.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Contact::class);
    }

    /**
     * Get the tenant that owns the order.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'tenant_id');
    }

    /**
     * Get the team that owns the order.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Team::class);
    }

    /**
     * Get the owner of the order.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_id');
    }

    /**
     * Get the user who created the order.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    /**
     * Get the transactions for the order.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(\App\Models\Commerce\CommerceTransaction::class, 'order_id');
    }

    /**
     * Scope a query to only include orders for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include orders for a specific team.
     */
    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Scope a query to only include orders with specific status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to search orders by order number.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where('order_number', 'like', "%{$search}%");
    }

    /**
     * Generate a unique order number.
     */
    public static function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'ORD-' . date('Y') . '-' . strtoupper(uniqid());
        } while (static::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }

    /**
     * Get formatted total with currency.
     */
    public function getFormattedTotalAttribute(): string
    {
        return $this->currency . ' ' . number_format($this->total_amount, 2);
    }

    /**
     * Check if order is paid.
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Check if order can be refunded.
     */
    public function canBeRefunded(): bool
    {
        return $this->status === 'paid';
    }
}
