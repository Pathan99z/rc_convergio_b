<?php

namespace App\Models\Commerce;

use App\Models\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderInvoice extends Model
{
    use HasFactory, HasTenantScope, SoftDeletes;

    protected $table = 'commerce_order_invoices';

    protected $fillable = [
        'tenant_id',
        'quote_id',
        'order_id',
        'contact_id',
        'deal_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'subtotal',
        'tax',
        'discount',
        'total',
        'currency',
        'status',
        'payment_method',
        'payment_reference',
        'paid_at',
        'pdf_path',
        'notes',
        'raw_payload',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the quote that owns the invoice.
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Quote::class);
    }

    /**
     * Get the order that owns the invoice.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(CommerceOrder::class, 'order_id');
    }

    /**
     * Get the contact associated with the invoice.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Contact::class);
    }

    /**
     * Get the deal associated with the invoice.
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Deal::class);
    }

    /**
     * Get the invoice items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderInvoiceItem::class, 'invoice_id')->orderBy('sort_order');
    }

    /**
     * Get the tenant that owns the invoice.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'tenant_id');
    }

    /**
     * Generate a unique invoice number.
     */
    public static function generateInvoiceNumber(int $tenantId): string
    {
        $year = date('Y');
        $prefix = "INV-{$year}-";
        
        // Get the last invoice number for this year and tenant
        $lastInvoice = static::where('tenant_id', $tenantId)
            ->where('invoice_number', 'like', "{$prefix}%")
            ->orderBy('invoice_number', 'desc')
            ->first();
        
        if ($lastInvoice) {
            // Extract sequence number
            $lastSequence = (int) substr($lastInvoice->invoice_number, strlen($prefix));
            $sequence = $lastSequence + 1;
        } else {
            $sequence = 1;
        }
        
        // Format with leading zeros (4 digits)
        $invoiceNumber = $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
        
        // Ensure uniqueness
        while (static::where('invoice_number', $invoiceNumber)->exists()) {
            $sequence++;
            $invoiceNumber = $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
        }
        
        return $invoiceNumber;
    }

    /**
     * Get the formatted total with currency.
     */
    public function getFormattedTotalAttribute(): string
    {
        $symbol = match(strtoupper($this->currency)) {
            'ZAR' => 'R',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => $this->currency,
        };
        
        return $symbol . ' ' . number_format($this->total, 2);
    }

    /**
     * Check if invoice is paid.
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Check if invoice is open.
     */
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Check if invoice is void.
     */
    public function isVoid(): bool
    {
        return $this->status === 'void';
    }

    /**
     * Scope to get paid invoices.
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope to get open invoices.
     */
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    /**
     * Scope to get void invoices.
     */
    public function scopeVoid($query)
    {
        return $query->where('status', 'void');
    }

    /**
     * Scope to filter by quote.
     */
    public function scopeForQuote($query, int $quoteId)
    {
        return $query->where('quote_id', $quoteId);
    }

    /**
     * Scope to filter by order.
     */
    public function scopeForOrder($query, int $orderId)
    {
        return $query->where('order_id', $orderId);
    }
}
