<?php

namespace App\Services;

use App\Models\ContactList;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ListService
{
    /**
     * Get paginated lists with filters
     */
    public function getLists(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ContactList::query()
            ->forTenant($filters['tenant_id'] ?? null)
            ->with(['creator:id,name,email']);

        // Apply filters
        if (!empty($filters['name'])) {
            $query->searchByName($filters['name']);
        }

        if (!empty($filters['type'])) {
            $query->byType($filters['type']);
        }

        if (!empty($filters['created_by'])) {
            $query->byCreator($filters['created_by']);
        }

        // Handle search query
        if (!empty($filters['q'])) {
            $searchTerm = $filters['q'];
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%");
            });
        }

        // Apply sorting
        $sortBy = $filters['sortBy'] ?? 'created_at';
        $sortOrder = $filters['sortOrder'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $lists = $query->paginate($perPage);

        // Calculate correct contact count for each list
        foreach ($lists->items() as $list) {
            if ($list->type === 'dynamic') {
                $list->contacts_count = $list->getContacts()->count();
            } else {
                // For static lists, use the existing count
                $list->contacts_count = $list->contacts()->count();
            }
        }

        return $lists;
    }

    /**
     * Create a new list
     */
    public function createList(array $data): ContactList
    {
        return ContactList::create($data);
    }

    /**
     * Update a list
     */
    public function updateList(ContactList $list, array $data): bool
    {
        return $list->update($data);
    }

    /**
     * Delete a list (soft delete)
     */
    public function deleteList(ContactList $list): bool
    {
        return $list->delete();
    }

    /**
     * Get list members (contacts)
     */
    public function getListMembers(ContactList $list, int $perPage = 15): LengthAwarePaginator
    {
        if ($list->type === 'static') {
            return $list->contacts()
                ->with(['company:id,name', 'owner:id,name'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
        }

        // For dynamic lists, apply rules
        $contacts = $list->getContacts()
            ->with(['company:id,name', 'owner:id,name'])
            ->orderBy('created_at', 'desc');

        return $contacts->paginate($perPage);
    }

    /**
     * Add contacts to a static list
     */
    public function addMembers(ContactList $list, array $contactIds): bool
    {
        if ($list->type !== 'static') {
            throw new \InvalidArgumentException('Can only add members to static lists');
        }

        // Filter out contacts that are already in the list
        $existingContactIds = $list->contacts()->pluck('contacts.id')->toArray();
        $newContactIds = array_diff($contactIds, $existingContactIds);

        if (empty($newContactIds)) {
            return true;
        }

        $list->contacts()->attach($newContactIds);

        return true;
    }

    /**
     * Remove a contact from a static list
     */
    public function removeMember(ContactList $list, int $contactId): bool
    {
        if ($list->type !== 'static') {
            throw new \InvalidArgumentException('Can only remove members from static lists');
        }

        $list->contacts()->detach($contactId);

        return true;
    }

    /**
     * Sync contacts for a static list (replace all existing contacts)
     */
    public function syncMembers(ContactList $list, array $contactIds): bool
    {
        if ($list->type !== 'static') {
            throw new \InvalidArgumentException('Can only sync members for static lists');
        }

        $list->contacts()->sync($contactIds);

        return true;
    }

    /**
     * Get list statistics
     */
    public function getListStats(ContactList $list): array
    {
        if ($list->type === 'static') {
            $totalContacts = $list->contacts()->count();
        } else {
            $totalContacts = $list->getContacts()->count();
        }

        return [
            'total_contacts' => $totalContacts,
            'type' => $list->type,
            'created_at' => $list->created_at,
            'updated_at' => $list->updated_at,
        ];
    }
}
