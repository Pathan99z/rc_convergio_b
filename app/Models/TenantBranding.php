<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantBranding extends Model
{
    use HasFactory;

    protected $table = 'tenant_branding';

    protected $fillable = [
        'tenant_id',
        'company_name',
        'logo_url',
        'primary_color',
        'secondary_color',
        'company_address',
        'company_phone',
        'company_email',
        'company_website',
        'invoice_footer',
        'email_signature',
        'custom_fields',
        'active',
    ];

    protected $casts = [
        'custom_fields' => 'array',
        'active' => 'boolean',
    ];

    /**
     * Get the tenant that owns the branding.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the default branding for a tenant.
     */
    public static function getDefaultBranding(int $tenantId): self
    {
        return static::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'company_name' => 'RC Convergio',
                'primary_color' => '#3b82f6',
                'secondary_color' => '#1f2937',
                'company_email' => 'billing@rcconvergio.com',
                'active' => true,
            ]
        );
    }

    /**
     * Get the logo URL with fallback.
     */
    public function getLogoUrlAttribute($value): ?string
    {
        if ($value) {
            return $value;
        }

        // Return default RC Convergio logo
        return '/images/rc-convergio-logo.png';
    }

    /**
     * Get the company name with fallback.
     */
    public function getCompanyNameAttribute($value): string
    {
        return $value ?: 'RC Convergio';
    }

    /**
     * Get formatted company address.
     */
    public function getFormattedAddressAttribute(): string
    {
        if (!$this->company_address) {
            return '';
        }

        return nl2br($this->company_address);
    }
}
