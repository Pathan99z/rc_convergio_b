<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Collaterals\StoreCollateralRequest;
use App\Http\Requests\Collaterals\SendCollateralRequest;
use App\Http\Resources\CollateralResource;
use App\Models\Collateral;
use App\Models\Contact;
use App\Models\Product;
use App\Services\CollateralService;
use App\Services\CollateralMailer;
use App\Services\TeamAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CollateralController extends Controller
{
    public function __construct(
        private CollateralService $collateralService,
        private CollateralMailer $collateralMailer,
        private TeamAccessService $teamAccessService
    ) {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Collateral::class);

        $tenantId = $request->user()->tenant_id;
        $query = Collateral::query()->where('tenant_id', $tenantId);

        // Filter by product
        if ($request->has('product_id')) {
            $query->where('product_id', $request->get('product_id'));
        }

        // Filter by collateral type
        if ($request->has('collateral_type')) {
            $query->byType($request->get('collateral_type'));
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        } else {
            // Default to active only
            $query->active();
        }

        // Search functionality
        if ($search = $request->query('search')) {
            $query->search($search);
        }

        // Sort
        $sortBy = $request->query('sortBy', 'created_at');
        $sortOrder = $request->query('sortOrder', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Apply team filtering if team access is enabled
        $this->teamAccessService->applyTeamFilter($query);

        $perPage = $request->query('per_page', 15);
        $collaterals = $query->with(['product', 'creator'])->paginate($perPage);

        return CollateralResource::collection($collaterals);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCollateralRequest $request): JsonResponse
    {
        $this->authorize('create', Collateral::class);

        try {
            $file = $request->file('file');
            $data = $request->only(['product_id', 'name', 'collateral_type', 'is_active']);

            $collateral = $this->collateralService->uploadCollateral($file, $data);

            return response()->json([
                'message' => 'Collateral uploaded successfully',
                'data' => new CollateralResource($collateral->load(['product', 'creator']))
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to upload collateral', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Failed to upload collateral',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Collateral $collateral): CollateralResource
    {
        $this->authorize('view', $collateral);

        return new CollateralResource($collateral->load(['product', 'creator']));
    }

    /**
     * Download the specified collateral file.
     */
    public function download(Request $request, $id): StreamedResponse
    {
        // Validate that ID is a valid integer
        if (!is_numeric($id) || $id <= 0) {
            abort(404, 'Collateral not found');
        }
        
        $user = $request->user();
        $tenantId = $user->tenant_id ?? $user->id;
        $collateral = Collateral::where('tenant_id', $tenantId)->findOrFail((int) $id);

        $this->authorize('view', $collateral);

        // Check if file exists
        if (!Storage::exists($collateral->file_path)) {
            abort(404, 'File not found');
        }

        return Storage::download($collateral->file_path, $collateral->name);
    }

    /**
     * Preview the specified collateral file (inline viewing).
     */
    public function preview(Request $request, $id)
    {
        // Validate that ID is a valid integer
        if (!is_numeric($id) || $id <= 0) {
            abort(404, 'Collateral not found');
        }
        
        $user = $request->user();
        
        // Check if user is authenticated
        if (!$user) {
            abort(401, 'Unauthorized');
        }
        
        $tenantId = $user->tenant_id ?? $user->id;
        $collateral = Collateral::where('tenant_id', $tenantId)->findOrFail((int) $id);

        $this->authorize('view', $collateral);

        // Check if file exists
        if (!Storage::exists($collateral->file_path)) {
            return response()->json([
                'error' => 'File not found',
                'message' => 'The collateral file is missing from storage. Please re-upload the collateral.',
                'file_path' => $collateral->file_path,
                'collateral_id' => $collateral->id,
                'name' => $collateral->name
            ], 404);
        }

        // Get file content and MIME type
        $fileContent = Storage::get($collateral->file_path);
        $mimeType = Storage::mimeType($collateral->file_path);

        // For images and PDFs, return inline content for preview
        if (in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'], true)) {
            return response($fileContent)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'inline; filename="' . $collateral->name . '"')
                ->header('Cache-Control', 'public, max-age=3600');
        }

        // For PowerPoint files, return as download (can't preview inline)
        if (in_array($mimeType, [
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation'
        ], true)) {
            return Storage::download($collateral->file_path, $collateral->name);
        }

        // For other file types, return as download
        return Storage::download($collateral->file_path, $collateral->name);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Collateral $collateral): JsonResponse
    {
        $this->authorize('update', $collateral);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'collateral_type' => 'sometimes|string|in:Brochures,PowerPoint Presentations,User Manuals,Infographics',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $collateral = $this->collateralService->updateCollateral($collateral, $validated);

            return response()->json([
                'message' => 'Collateral updated successfully',
                'data' => new CollateralResource($collateral->load(['product', 'creator']))
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update collateral', [
                'error' => $e->getMessage(),
                'collateral_id' => $collateral->id,
            ]);

            return response()->json([
                'message' => 'Failed to update collateral',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Collateral $collateral): JsonResponse
    {
        $this->authorize('delete', $collateral);

        try {
            $this->collateralService->deleteCollateral($collateral);

            return response()->json([
                'message' => 'Collateral deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete collateral', [
                'error' => $e->getMessage(),
                'collateral_id' => $collateral->id,
            ]);

            return response()->json([
                'message' => 'Failed to delete collateral',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get collaterals for a specific product.
     */
    public function getByProduct(Request $request, Product $product): AnonymousResourceCollection
    {
        $this->authorize('view', $product);

        $query = Collateral::where('product_id', $product->id)
            ->where('tenant_id', $request->user()->tenant_id);

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        } else {
            $query->active();
        }

        // Apply team filtering if team access is enabled
        $this->teamAccessService->applyTeamFilter($query);

        $collaterals = $query->with(['product', 'creator'])->get();

        return CollateralResource::collection($collaterals);
    }

    /**
     * Get available collateral types.
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'data' => [
                'Brochures',
                'PowerPoint Presentations',
                'User Manuals',
                'Infographics'
            ]
        ]);
    }

    /**
     * Send collateral(s) to a contact.
     */
    public function send(SendCollateralRequest $request): JsonResponse
    {
        $this->authorize('send', Collateral::class);

        try {
            $tenantId = $request->user()->tenant_id;
            
            // Load contact
            $contact = Contact::where('id', $request->get('contact_id'))
                ->where('tenant_id', $tenantId)
                ->firstOrFail();

            if (!$contact->email) {
                return response()->json([
                    'message' => 'Contact does not have a valid email address'
                ], 422);
            }

            // Load collaterals
            $collaterals = Collateral::whereIn('id', $request->get('collateral_ids'))
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->with('product')
                ->get();

            if ($collaterals->isEmpty()) {
                return response()->json([
                    'message' => 'No valid collaterals found'
                ], 422);
            }

            // Send email
            $success = $this->collateralMailer->sendCollaterals(
                $contact,
                $collaterals,
                $request->get('message')
            );

            if (!$success) {
                return response()->json([
                    'message' => 'Failed to send collateral email'
                ], 500);
            }

            return response()->json([
                'message' => 'Collateral sent successfully',
                'data' => [
                    'contact_id' => $contact->id,
                    'contact_name' => $contact->first_name . ' ' . $contact->last_name,
                    'collaterals_sent' => $collaterals->count(),
                    'sent_at' => now()->toISOString(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send collateral', [
                'error' => $e->getMessage(),
                'contact_id' => $request->get('contact_id'),
                'collateral_ids' => $request->get('collateral_ids'),
            ]);

            return response()->json([
                'message' => 'Failed to send collateral',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get sent collaterals for a contact.
     */
    public function getSentForContact(Request $request, Contact $contact): JsonResponse
    {
        $this->authorize('view', $contact);

        $tenantId = $request->user()->tenant_id;

        $sentCollaterals = \App\Models\CollateralSent::where('contact_id', $contact->id)
            ->where('tenant_id', $tenantId)
            ->with(['collateral.product', 'sender'])
            ->orderBy('sent_at', 'desc')
            ->get();

        return response()->json([
            'data' => $sentCollaterals->map(function ($sent) {
                return [
                    'id' => $sent->id,
                    'collateral' => new CollateralResource($sent->collateral),
                    'message' => $sent->message,
                    'sent_at' => $sent->sent_at->toISOString(),
                    'sent_by' => [
                        'id' => $sent->sender->id,
                        'name' => $sent->sender->name,
                        'email' => $sent->sender->email,
                    ],
                ];
            })
        ]);
    }
}

