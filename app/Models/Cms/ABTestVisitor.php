<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ABTestVisitor extends Model
{
    use HasFactory;

    protected $table = 'cms_ab_test_visitors';

    protected $fillable = [
        'ab_test_id',
        'visitor_id',
        'variant_shown',
        'converted',
        'conversion_data',
        'ip_address',
        'user_agent',
        'referrer',
        'visited_at',
        'converted_at',
    ];

    protected $casts = [
        'conversion_data' => 'array',
        'converted' => 'boolean',
        'visited_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    /**
     * Get the A/B test this visitor belongs to.
     */
    public function abTest(): BelongsTo
    {
        return $this->belongsTo(ABTest::class, 'ab_test_id');
    }

    /**
     * Scope to get converted visitors.
     */
    public function scopeConverted($query)
    {
        return $query->where('converted', true);
    }

    /**
     * Scope to get visitors for a specific variant.
     */
    public function scopeForVariant($query, string $variant)
    {
        return $query->where('variant_shown', $variant);
    }

    /**
     * Mark visitor as converted.
     */
    public function markAsConverted(array $conversionData = []): void
    {
        $this->update([
            'converted' => true,
            'converted_at' => now(),
            'conversion_data' => $conversionData
        ]);
    }
}



