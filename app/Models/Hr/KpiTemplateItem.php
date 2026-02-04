<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiTemplateItem extends Model
{
    use HasFactory;

    protected $table = 'hr_kpi_template_items';

    protected $fillable = [
        'kpi_template_id',
        'name',
        'weight',
        'description',
        'order',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'order' => 'integer',
    ];

    /**
     * Get the KPI template this item belongs to.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(KpiTemplate::class, 'kpi_template_id');
    }

    /**
     * Get all review items for this template item.
     */
    public function reviewItems(): HasMany
    {
        return $this->hasMany(KpiReviewItem::class, 'kpi_template_item_id');
    }
}

