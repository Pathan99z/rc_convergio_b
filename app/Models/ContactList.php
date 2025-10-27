<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactList extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lists';

    protected $fillable = [
        'name',
        'description',
        'type',
        'status',
        'rule',
        'archived_at',
        'cancelled_at',
        'created_by',
        'tenant_id',
    ];

    protected $casts = [
        'rule' => 'array',
        'archived_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Get the user who created the list.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the tenant that owns the list.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the contacts that belong to the list.
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'list_members', 'list_id', 'contact_id')
            ->withTimestamps();
    }

    /**
     * Get the list members.
     */
    public function members(): HasMany
    {
        return $this->hasMany(ListMember::class, 'list_id');
    }

    /**
     * Scope a query to only include lists for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to filter by type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to filter by creator.
     */
    public function scopeByCreator($query, $creatorId)
    {
        return $query->where('created_by', $creatorId);
    }

    /**
     * Scope a query to search by name.
     */
    public function scopeSearchByName($query, $name)
    {
        return $query->where('name', 'like', "%{$name}%");
    }

    /**
     * Get contacts based on list type and rules.
     */
    public function getContacts()
    {
        if ($this->type === 'static') {
            return $this->contacts()->forTenant($this->tenant_id);
        }

        // Dynamic list - apply rules
        return $this->applyDynamicRules();
    }

    /**
     * Apply dynamic rules to get contacts.
     */
    private function applyDynamicRules()
    {
        if (!$this->rule) {
            return Contact::where('tenant_id', $this->tenant_id)->whereRaw('1 = 0'); // Empty result
        }

        $query = Contact::where('tenant_id', $this->tenant_id);

        foreach ($this->rule as $condition) {
            $this->applyCondition($query, $condition);
        }

        return $query;
    }

    /**
     * Apply a single condition to the query.
     */
    private function applyCondition($query, $condition)
    {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? 'equals';
        $value = $condition['value'] ?? '';

        switch ($field) {
            case 'first_name':
            case 'last_name':
            case 'email':
            case 'phone':
            case 'lifecycle_stage':
            case 'source':
                $this->applyStringCondition($query, $field, $operator, $value);
                break;
            case 'tags':
                if ($operator === 'contains') {
                    $query->whereJsonContains($field, $value);
                }
                break;
            case 'company.name':
                $query->whereHas('company', function ($q) use ($operator, $value) {
                    $this->applyStringCondition($q, 'name', $operator, $value);
                });
                break;
            case 'company.industry':
                $query->whereHas('company', function ($q) use ($operator, $value) {
                    $this->applyStringCondition($q, 'industry', $operator, $value);
                });
                break;
            case 'company.size':
                $query->whereHas('company', function ($q) use ($operator, $value) {
                    $this->applyNumericCondition($q, 'size', $operator, $value);
                });
                break;
            // Frontend field name mappings
            case 'company_size':
                $query->whereHas('company', function ($q) use ($operator, $value) {
                    $this->applyNumericCondition($q, 'size', $operator, $value);
                });
                break;
            case 'industry':
                $query->whereHas('company', function ($q) use ($operator, $value) {
                    $this->applyStringCondition($q, 'industry', $operator, $value);
                });
                break;
            case 'created_at':
                $this->applyDateCondition($query, $field, $operator, $value);
                break;
        }
    }

    /**
     * Apply string-based conditions with new operator format.
     */
    private function applyStringCondition($query, $field, $operator, $value)
    {
        switch ($operator) {
            case 'equals':
                $query->where($field, '=', $value);
                break;
            case 'not_equals':
                $query->where($field, '!=', $value);
                break;
            case 'contains':
                $query->where($field, 'like', "%{$value}%");
                break;
            case 'starts_with':
                $query->where($field, 'like', "{$value}%");
                break;
            case 'ends_with':
                $query->where($field, 'like', "%{$value}");
                break;
            case 'greater_than':
                $query->where($field, '>', $value);
                break;
            case 'less_than':
                $query->where($field, '<', $value);
                break;
            default:
                // Fallback to equals for unknown operators
                $query->where($field, '=', $value);
                break;
        }
    }

    /**
     * Apply numeric-based conditions with new operator format.
     */
    private function applyNumericCondition($query, $field, $operator, $value)
    {
        switch ($operator) {
            case 'equals':
                $query->where($field, '=', (int)$value);
                break;
            case 'not_equals':
                $query->where($field, '!=', (int)$value);
                break;
            case 'greater_than':
                $query->where($field, '>', (int)$value);
                break;
            case 'less_than':
                $query->where($field, '<', (int)$value);
                break;
            default:
                // Fallback to equals for unknown operators
                $query->where($field, '=', (int)$value);
                break;
        }
    }

    /**
     * Apply date-based conditions with new operator format.
     */
    private function applyDateCondition($query, $field, $operator, $value)
    {
        switch ($operator) {
            case 'equals':
                $query->whereDate($field, '=', $value);
                break;
            case 'not_equals':
                $query->whereDate($field, '!=', $value);
                break;
            case 'greater_than':
                $query->whereDate($field, '>', $value);
                break;
            case 'less_than':
                $query->whereDate($field, '<', $value);
                break;
            default:
                // Fallback to equals for unknown operators
                $query->whereDate($field, '=', $value);
                break;
        }
    }
}
