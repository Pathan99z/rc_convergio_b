<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailSetting extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'mail_settings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'setting_key',
        'setting_value',
        'tenant_id',
    ];

    /**
     * Get the tenant that owns the mail setting.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Scope a query to only include settings for a specific tenant.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $tenantId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Get or create mail setting for a tenant.
     *
     * @param int $tenantId
     * @param string $key
     * @param string|null $value
     * @return self
     */
    public static function getOrCreateForTenant(int $tenantId, string $key, ?string $value = null): self
    {
        return static::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'setting_key' => $key,
            ],
            [
                'setting_value' => $value,
            ]
        );
    }

    /**
     * Update or create mail setting for a tenant.
     *
     * @param int $tenantId
     * @param string $key
     * @param string|null $value
     * @return self
     */
    public static function updateOrCreateForTenant(int $tenantId, string $key, ?string $value = null): self
    {
        return static::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'setting_key' => $key,
            ],
            [
                'setting_value' => $value,
            ]
        );
    }
}

