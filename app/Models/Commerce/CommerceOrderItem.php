<?php

namespace App\Models\Commerce;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'name',
        'quantity',
        'unit_price',
        'discount',
        'tax_rate',
        'subtotal',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the order that owns the item.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(CommerceOrder::class, 'order_id');
    }

    /**
     * Get the product that owns the item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Product::class);
    }

    /**
     * Calculate the subtotal for this item.
     */
    public function calculateSubtotal(): float
    {
        $subtotal = ($this->unit_price * $this->quantity) - $this->discount;
        $tax = $subtotal * ($this->tax_rate / 100);
        return $subtotal + $tax;
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::saving(function ($item) {
            $item->subtotal = $item->calculateSubtotal();
        });
    }
}
