<?php

namespace App\Models\Commerce;

use App\Models\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionInvoice extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'commerce_invoices';

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'stripe_invoice_id',
        'amount_cents',
        'currency',
        'status',
        'paid_at',
        'raw_payload',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'paid_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the subscription that owns the invoice.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    /**
     * Get the formatted amount.
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount_cents / 100, 2);
    }

    /**
     * Get the amount in dollars.
     */
    public function getAmountDollarsAttribute(): float
    {
        return $this->amount_cents / 100;
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
}
