<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiReviewItem extends Model
{
    use HasFactory;

    protected $table = 'hr_kpi_review_items';

    protected $fillable = [
        'kpi_review_id',
        'kpi_template_item_id',
        'score',
        'comments',
    ];

    protected $casts = [
        'score' => 'decimal:2',
    ];

    /**
     * Get the KPI review this item belongs to.
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(KpiReview::class, 'kpi_review_id');
    }

    /**
     * Get the template item this review item is for.
     */
    public function templateItem(): BelongsTo
    {
        return $this->belongsTo(KpiTemplateItem::class, 'kpi_template_item_id');
    }
}

