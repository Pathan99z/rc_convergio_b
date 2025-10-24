<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DocumentRelationship extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'related_type',
        'related_id',
        'tenant_id',
        'created_by',
    ];

    protected $casts = [
        'related_id' => 'integer',
        'document_id' => 'integer',
        'tenant_id' => 'integer',
        'created_by' => 'integer',
    ];

    /**
     * Get the document that owns the relationship.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Get the related model (polymorphic).
     */
    public function related(): MorphTo
    {
        return $this->morphTo('related', 'related_type', 'related_id');
    }

    /**
     * Get the tenant that owns the relationship.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the user who created the relationship.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}




