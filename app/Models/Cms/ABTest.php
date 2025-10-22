<?php

namespace App\Models\Cms;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class ABTest extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cms_ab_tests';

    protected $fillable = [
        'name',
        'description',
        'page_id',
        'variant_a_id',
        'variant_b_id',
        'traffic_split',
        'status',
        'performance_data',
        'goals',
        'started_at',
        'ended_at',
        'min_sample_size',
        'confidence_level',
        'created_by',
    ];

    protected $casts = [
        'performance_data' => 'array',
        'goals' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'confidence_level' => 'decimal:2',
    ];

    /**
     * Get the main page for this test.
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * Get variant A (original).
     */
    public function variantA(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'variant_a_id');
    }

    /**
     * Get variant B (test).
     */
    public function variantB(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'variant_b_id');
    }

    /**
     * Get the user who created this test.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all visitors for this test.
     */
    public function visitors(): HasMany
    {
        return $this->hasMany(ABTestVisitor::class, 'ab_test_id');
    }

    /**
     * Scope to get running tests.
     */
    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    /**
     * Scope to get completed tests.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Check if test is currently running.
     */
    public function isRunning(): bool
    {
        return $this->status === 'running' && 
               $this->started_at && 
               $this->started_at->isPast() &&
               (!$this->ended_at || $this->ended_at->isFuture());
    }

    /**
     * Determine which variant to show for a visitor.
     */
    public function getVariantForVisitor(string $visitorId): string
    {
        // Use consistent hash to ensure same visitor always sees same variant
        $hash = crc32($visitorId . $this->id);
        return ($hash % 100) < $this->traffic_split ? 'b' : 'a';
    }

    /**
     * Get conversion rate for a variant.
     */
    public function getConversionRate(string $variant): float
    {
        $totalVisitors = $this->visitors()->where('variant_shown', $variant)->count();
        $conversions = $this->visitors()->where('variant_shown', $variant)->where('converted', true)->count();
        
        return $totalVisitors > 0 ? ($conversions / $totalVisitors) * 100 : 0;
    }

    /**
     * Get statistical significance.
     */
    public function getStatisticalSignificance(): array
    {
        $conversionA = $this->getConversionRate('a');
        $conversionB = $this->getConversionRate('b');
        $visitorsA = $this->visitors()->where('variant_shown', 'a')->count();
        $visitorsB = $this->visitors()->where('variant_shown', 'b')->count();

        // Simple statistical significance calculation
        $isSignificant = $visitorsA >= $this->min_sample_size && 
                        $visitorsB >= $this->min_sample_size &&
                        abs($conversionA - $conversionB) > 2; // 2% difference threshold

        return [
            'is_significant' => $isSignificant,
            'conversion_a' => $conversionA,
            'conversion_b' => $conversionB,
            'visitors_a' => $visitorsA,
            'visitors_b' => $visitorsB,
            'winner' => $conversionB > $conversionA ? 'b' : 'a',
            'improvement' => $conversionA > 0 ? (($conversionB - $conversionA) / $conversionA) * 100 : 0
        ];
    }
}



