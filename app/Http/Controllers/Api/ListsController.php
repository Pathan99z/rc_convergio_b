<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lists\StoreListRequest;
use App\Http\Requests\Lists\UpdateListRequest;
use App\Http\Resources\ListResource;
use App\Http\Resources\ContactResource;
use App\Models\ContactList;
use App\Services\ListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class ListsController extends Controller
{
    public function __construct(
        private ListService $listService
    ) {}

    /**
     * Display a listing of lists.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = [
            'tenant_id' => $request->user()->id, // Use authenticated user as tenant
            'q' => $request->get('q'),
            'name' => $request->get('name'),
            'type' => $request->get('type'),
            'created_by' => $request->get('created_by'),
            'sortBy' => $request->get('sortBy', 'created_at'),
            'sortOrder' => $request->get('sortOrder', 'desc'),
        ];

        $lists = $this->listService->getLists($filters, $request->get('per_page', 15));

        return ListResource::collection($lists);
    }

    /**
     * Store a newly created list.
     */
    public function store(StoreListRequest $request): JsonResource
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $data['tenant_id'] = $request->user()->id; // Use authenticated user as tenant

        $list = $this->listService->createList($data);

        // Handle contacts for static lists
        if ($list->type === 'static' && $request->has('contacts') && is_array($request->contacts)) {
            $this->listService->addMembers($list, $request->contacts);
        }

        // Load the contact count for the response
        if ($list->type === 'dynamic') {
            $list->contacts_count = $list->getContacts()->count();
        } else {
            $list->loadCount('contacts');
        }

        return new ListResource($list);
    }

    /**
     * Display the specified list.
     */
    public function show(ContactList $list): JsonResource
    {
        $this->authorize('view', $list);

        $list->load(['creator:id,name,email']);

        // For dynamic segments, calculate count based on rules
        if ($list->type === 'dynamic') {
            $list->contacts_count = $list->getContacts()->count();
        } else {
            $list->loadCount('contacts');
        }

        return new ListResource($list);
    }

    /**
     * Update the specified list.
     */
    public function update(UpdateListRequest $request, ContactList $list): JsonResource
    {
        $this->authorize('update', $list);

        $data = $request->validated();
        
        $this->listService->updateList($list, $data);

        // Handle contacts for static lists
        if ($list->type === 'static' && $request->has('contacts') && is_array($request->contacts)) {
            $this->listService->syncMembers($list, $request->contacts);
        }

        // Refresh the list and load the updated contact count
        $list->refresh();
        if ($list->type === 'dynamic') {
            $list->contacts_count = $list->getContacts()->count();
        } else {
            $list->loadCount('contacts');
        }
        
        return new ListResource($list);
    }

    /**
     * Remove the specified list.
     */
    public function destroy(ContactList $list): JsonResponse
    {
        $this->authorize('delete', $list);

        $this->listService->deleteList($list);

        return response()->json(['message' => 'List deleted successfully']);
    }

    /**
     * Get list members (contacts).
     */
    public function members(Request $request, ContactList $list): AnonymousResourceCollection
    {
        $this->authorize('view', $list);

        $members = $this->listService->getListMembers($list, $request->get('per_page', 15));

        return ContactResource::collection($members);
    }

    /**
     * Add members to a static list.
     */
    public function addMembers(Request $request, ContactList $list): JsonResponse
    {
        $this->authorize('update', $list);

        $request->validate([
            'contact_ids' => ['required', 'array'],
            'contact_ids.*' => ['integer', 'exists:contacts,id'],
        ]);

        try {
            $this->listService->addMembers($list, $request->contact_ids);

            return response()->json(['message' => 'Members added successfully']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Remove a member from a static list.
     */
    public function removeMember(Request $request, ContactList $list, int $contactId): JsonResponse
    {
        $this->authorize('update', $list);

        try {
            $this->listService->removeMember($list, $contactId);

            return response()->json(['message' => 'Member removed successfully']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Check if a list name already exists.
     */
    public function checkDuplicate(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'exclude_id' => 'nullable|integer|exists:lists,id'
        ]);

        $name = $request->get('name');
        $excludeId = $request->get('exclude_id');
        $tenantId = $request->user()->id;

        $query = ContactList::where('tenant_id', $tenantId)
                    ->whereRaw('LOWER(name) = ?', [strtolower($name)]);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $exists = $query->exists();

        return response()->json(['exists' => $exists]);
    }
}
