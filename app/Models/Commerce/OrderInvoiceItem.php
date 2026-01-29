<?php

namespace App\Models\Commerce;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderInvoiceItem extends Model
{
    use HasFactory;

    protected $table = 'commerce_order_invoice_items';

    protected $fillable = [
        'invoice_id',
        'product_id',
        'name',
        'description',
        'quantity',
        'unit_price',
        'discount',
        'tax_rate',
        'tax_amount',
        'line_total',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function ($item) {
            // Auto-calculate line total if not set
            if (!isset($item->line_total) || $item->line_total == 0) {
                $item->calculateLineTotal();
            }
        });
    }

    /**
     * Get the invoice that owns the item.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(OrderInvoice::class, 'invoice_id');
    }

    /**
     * Get the product associated with this item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Product::class);
    }

    /**
     * Calculate the line total.
     */
    public function calculateLineTotal(): void
    {
        $subtotal = $this->quantity * $this->unit_price;
        $discountedAmount = $subtotal - ($this->discount ?? 0);
        $taxAmount = $discountedAmount * (($this->tax_rate ?? 0) / 100);
        
        $this->tax_amount = $taxAmount;
        $this->line_total = $discountedAmount + $taxAmount;
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
        return $this->subtotal - ($this->discount ?? 0);
    }
}
