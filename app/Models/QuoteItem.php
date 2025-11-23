<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quote_id',
        'product_id',
        'name',
        'description',
        'quantity',
        'unit_price',
        'original_unit_price',
        'original_currency',
        'exchange_rate_used',
        'converted_at',
        'discount',
        'tax_rate',
        'total',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'original_unit_price' => 'decimal:2',
        'exchange_rate_used' => 'decimal:6',
        'converted_at' => 'datetime',
        'discount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'total' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function ($quoteItem) {
            // Auto-calculate total when saving
            $quoteItem->calculateTotal();
        });
    }

    /**
     * Get the quote that owns the quote item.
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * Get the product associated with this quote item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calculate the total for this line item.
     */
    public function calculateTotal(): void
    {
        $subtotal = $this->quantity * $this->unit_price;
        $discountedAmount = $subtotal - $this->discount;
        $taxAmount = $discountedAmount * ($this->tax_rate / 100);
        
        $this->total = $discountedAmount + $taxAmount;
    }

    /**
     * Get the subtotal (quantity * unit_price).
     */
    public function getSubtotalAttribute(): float
    {
        return $this->quantity * $this->unit_price;
    }

    /**
     * Get the discounted amount.
     */
    public function getDiscountedAmountAttribute(): float
    {
        return $this->subtotal - $this->discount;
    }

    /**
     * Get the tax amount.
     */
    public function getTaxAmountAttribute(): float
    {
        return $this->discounted_amount * ($this->tax_rate / 100);
    }

    /**
     * Scope a query to order by sort order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
