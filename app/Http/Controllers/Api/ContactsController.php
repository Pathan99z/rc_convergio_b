<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contacts\StoreContactRequest;
use App\Http\Requests\Contacts\UpdateContactRequest;
use App\Jobs\ImportContactsJob;
use App\Models\Contact;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ContactsController extends Controller
{
    public function __construct(private CacheRepository $cache) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Contact::class);

        $tenantId = (int) $request->header('X-Tenant-ID');
        $userId = $request->user()->id;

        $query = Contact::query()->where('tenant_id', $tenantId);

        // Filter by owner_id to ensure users only see their own contacts
        $query->where('owner_id', $userId);

        if ($ownerId = $request->query('owner_id')) {
            $query->where('owner_id', $ownerId);
        }
        if ($stage = $request->query('stage')) {
            $query->where('lifecycle_stage', $stage);
        }
        if ($from = $request->query('created_from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('created_to')) {
            $query->whereDate('created_at', '<=', $to);
        }
        if ($tag = $request->query('tag')) {
            $query->whereJsonContains('tags', $tag);
        }

        $sort = (string) $request->query('sort', '-updated_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $query->orderBy($column, $direction);

        $perPage = min((int) $request->query('per_page', 15), 100);
        $contacts = $query->paginate($perPage);

        return response()->json([
            'data' => $contacts->items(),
            'meta' => [
                'current_page' => $contacts->currentPage(),
                'last_page' => $contacts->lastPage(),
                'per_page' => $contacts->perPage(),
                'total' => $contacts->total(),
                'from' => $contacts->firstItem(),
                'to' => $contacts->lastItem(),
            ],
        ]);
    }

    public function store(StoreContactRequest $request): JsonResponse
    {
        $this->authorize('create', Contact::class);

        $tenantId = (int) $request->header('X-Tenant-ID');

        // Idempotency via table with 5-minute window
        $idempotencyKey = (string) $request->header('Idempotency-Key', '');
        $cacheKey = null;
        if ($idempotencyKey !== '') {
            $existing = DB::table('idempotency_keys')
                ->where('user_id', $request->user()->id)
                ->where('route', 'contacts.store')
                ->where('key', $idempotencyKey)
                ->where('created_at', '>=', now()->subMinutes(5))
                ->first();
            if ($existing) {
                return response()->json(json_decode($existing->response, true));
            }
        }

        $data = $request->validated();
        $data['tenant_id'] = $tenantId;

        $contact = Contact::create($data);

        $response = [
            'data' => $contact,
            'meta' => [ 'page' => 1, 'total' => 1 ],
        ];

        if (! empty($idempotencyKey)) {
            DB::table('idempotency_keys')->insert([
                'user_id' => $request->user()->id,
                'route' => 'contacts.store',
                'key' => $idempotencyKey,
                'response' => json_encode($response),
                'created_at' => now(),
            ]);
        }

        return response()->json($response, 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) $request->header('X-Tenant-ID');
        $userId = $request->user()->id;
        $contact = Contact::where('tenant_id', $tenantId)
                         ->where('owner_id', $userId)
                         ->findOrFail($id);
        $this->authorize('view', $contact);

        return response()->json([
            'data' => [
                'contact' => $contact,
                'timeline_summary' => [],
            ],
            'meta' => [ 'page' => 1, 'total' => 1 ],
        ]);
    }

    public function update(UpdateContactRequest $request, int $id): JsonResponse
    {
        $tenantId = (int) $request->header('X-Tenant-ID');
        $userId = $request->user()->id;
        $contact = Contact::where('tenant_id', $tenantId)
                         ->where('owner_id', $userId)
                         ->findOrFail($id);
        $this->authorize('update', $contact);

        $contact->fill($request->validated());
        $contact->save();

        return response()->json([
            'data' => $contact,
            'meta' => [ 'page' => 1, 'total' => 1 ],
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) $request->header('X-Tenant-ID');
        $userId = $request->user()->id;
        $contact = Contact::where('tenant_id', $tenantId)
                         ->where('owner_id', $userId)
                         ->findOrFail($id);
        $this->authorize('delete', $contact);

        $contact->delete();

        return response()->json([
            'data' => null,
            'meta' => [ 'page' => 1, 'total' => 0 ],
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $this->authorize('create', Contact::class);
        $tenantId = (int) $request->header('X-Tenant-ID');

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $path = $request->file('file')->store('imports/contacts');

        $job = new ImportContactsJob($path, $tenantId, $request->user()->id);
        dispatch($job);

        return response()->json([
            'data' => [ 'job_id' => spl_object_hash($job) ],
            'meta' => [ 'page' => 1, 'total' => 1 ],
        ], 202);
    }

    public function search(Request $request): JsonResponse
    {
        $tenantId = (int) $request->header('X-Tenant-ID');
        $q = (string) $request->query('q', '');

        $results = Contact::query()
            ->where('tenant_id', $tenantId)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('first_name', 'like', "%$q%")
                        ->orWhere('last_name', 'like', "%$q%")
                        ->orWhere('email', 'like', "%$q%")
                        ->orWhere('phone', 'like', "%$q%");
                });
            })
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => $results,
            'meta' => [ 'page' => 1, 'total' => $results->count() ],
        ]);
    }
}


