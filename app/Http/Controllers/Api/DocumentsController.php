<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\DocumentService;
use App\Services\TeamAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentsController extends Controller
{
    public function __construct(
        private DocumentService $documentService,
        private TeamAccessService $teamAccessService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Document::class);

        $user = $request->user();
        $tenantId = $user->tenant_id ?? $user->id;


        $query = Document::where('tenant_id', $tenantId);

        // Apply team filtering if team access is enabled
        if ($this->teamAccessService->enabled() && $user->team_id) {
            $query->where(function ($q) use ($user) {
                $q->where('team_id', $user->team_id)
                  ->orWhere('owner_id', $user->id)
                  ->orWhere('visibility', 'tenant');
            });
        }

        // Apply filters
        if ($request->has('search') && !empty($request->get('search'))) {
            $query->search($request->get('search'));
        }

        if ($request->has('file_type') && !empty($request->get('file_type'))) {
            $query->byFileType($request->get('file_type'));
        }

        if ($request->has('visibility') && !empty($request->get('visibility'))) {
            $query->withVisibility($request->get('visibility'));
        }

        if ($request->has('related_type') && $request->has('related_id') && 
            !empty($request->get('related_type')) && !empty($request->get('related_id'))) {
            $query->relatedTo($request->get('related_type'), $request->get('related_id'));
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min((int) $request->query('per_page', 15), 100);
        $documents = $query->with(['owner', 'creator', 'team', 'related'])->paginate($perPage);


        return response()->json([
            'data' => $documents->items(),
            'meta' => [
                'current_page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
                'per_page' => $documents->perPage(),
                'total' => $documents->total(),
                'from' => $documents->firstItem(),
                'to' => $documents->lastItem(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Document::class);

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:102400', // 100MB max
            'title' => 'nullable|string|max:255',
            'visibility' => 'nullable|in:private,team,tenant',
            'related_type' => 'nullable|string',
            'related_id' => 'nullable|integer',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $file = $request->file('file');
        $metadata = $request->only(['title', 'visibility', 'related_type', 'related_id', 'metadata']);

        $document = $this->documentService->uploadDocument($file, $metadata);

        return response()->json([
            'data' => $document->load(['owner', 'creator', 'team', 'related']),
            'message' => 'Document uploaded successfully',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, $id): JsonResponse
    {
        // Validate that ID is a valid integer
        if (!is_numeric($id) || $id <= 0) {
            abort(404, 'Document not found');
        }
        
        $user = $request->user();
        $tenantId = $user->tenant_id ?? $user->id;
        $document = Document::where('tenant_id', $tenantId)->findOrFail((int) $id);

        $this->authorize('view', $document);

        return response()->json([
            'data' => $document->load(['owner', 'creator', 'team', 'related']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        // Validate that ID is a valid integer
        if (!is_numeric($id) || $id <= 0) {
            abort(404, 'Document not found');
        }
        
        $user = $request->user();
        $tenantId = $user->tenant_id ?? $user->id;
        $document = Document::where('tenant_id', $tenantId)->findOrFail((int) $id);

        $this->authorize('update', $document);

        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'visibility' => 'nullable|in:private,team,tenant',
            'related_type' => 'nullable|string',
            'related_id' => 'nullable|integer',
            'metadata' => 'nullable|array',
            'owner_id' => 'nullable|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->only(['title', 'description', 'visibility', 'related_type', 'related_id', 'metadata', 'owner_id']);
        $document = $this->documentService->updateDocument($document, $data);

        return response()->json([
            'data' => $document->load(['owner', 'creator', 'team', 'related']),
            'message' => 'Document updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        // Validate that ID is a valid integer
        if (!is_numeric($id) || $id <= 0) {
            abort(404, 'Document not found');
        }
        
        $user = $request->user();
        $tenantId = $user->tenant_id ?? $user->id;
        $document = Document::where('tenant_id', $tenantId)->findOrFail((int) $id);

        $this->authorize('delete', $document);

        $this->documentService->deleteDocument($document);

        return response()->json([
            'message' => 'Document deleted successfully',
        ]);
    }

    /**
     * Download the specified document.
     */
    public function download(Request $request, $id): StreamedResponse
    {
        // Validate that ID is a valid integer
        if (!is_numeric($id) || $id <= 0) {
            abort(404, 'Document not found');
        }
        
        $user = $request->user();
        $tenantId = $user->tenant_id ?? $user->id;
        $document = Document::where('tenant_id', $tenantId)->findOrFail((int) $id);

        $this->authorize('download', $document);

        // Record the view
        $this->documentService->recordView($document, $user);

        // Check if file exists
        if (!Storage::exists($document->file_path)) {
            abort(404, 'File not found');
        }

        return Storage::download($document->file_path, $document->title);
    }

    /**
     * Get document analytics.
     */
    public function analytics(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Document::class);

        $user = $request->user();
        $tenantId = $user->tenant_id ?? $user->id;
        $teamId = $request->get('team_id');

        $analytics = $this->documentService->getAnalytics($tenantId, $teamId);

        return response()->json([
            'data' => $analytics,
        ]);
    }

    /**
     * Preview the specified document.
     */
    public function preview(Request $request, $id)
    {
        // Validate that ID is a valid integer
        if (!is_numeric($id) || $id <= 0) {
            abort(404, 'Document not found');
        }
        
        $user = $request->user();
        
        // Check if user is authenticated
        if (!$user) {
            abort(401, 'Unauthorized');
        }
        
        $tenantId = $user->tenant_id ?? $user->id;
        $document = Document::where('tenant_id', $tenantId)->findOrFail((int) $id);

        $this->authorize('view', $document);

        // Record the view
        $this->documentService->recordView($document, $user);

        // Check if file exists
        if (!Storage::exists($document->file_path)) {
            return response()->json([
                'error' => 'File not found',
                'message' => 'The document file is missing from storage. Please re-upload the document.',
                'file_path' => $document->file_path,
                'document_id' => $document->id,
                'title' => $document->title
            ], 404);
        }

        // Get file content and MIME type
        $fileContent = Storage::get($document->file_path);
        $mimeType = Storage::mimeType($document->file_path);

        // For images and PDFs, return inline content for preview
        if (in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'])) {
            return response($fileContent)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'inline; filename="' . $document->title . '"')
                ->header('Cache-Control', 'public, max-age=3600');
        }

        // For text files, return as plain text
        if (str_starts_with($mimeType, 'text/')) {
            return response($fileContent)
                ->header('Content-Type', 'text/plain; charset=utf-8')
                ->header('Content-Disposition', 'inline; filename="' . $document->title . '"');
        }

        // For other file types, return as download
        return response($fileContent)
            ->header('Content-Type', $mimeType)
            ->header('Content-Disposition', 'attachment; filename="' . $document->title . '"');
    }

    /**
     * Link document to a related record.
     */
    public function link(Request $request, $id): JsonResponse
    {
        // Validate that ID is a valid integer
        if (!is_numeric($id) || $id <= 0) {
            abort(404, 'Document not found');
        }
        
        $user = $request->user();
        $tenantId = $user->tenant_id ?? $user->id;
        $document = Document::where('tenant_id', $tenantId)->findOrFail((int) $id);

        $this->authorize('update', $document);

        $validator = Validator::make($request->all(), [
            'related_type' => 'required|string',
            'related_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $document = $this->documentService->linkToRecord(
            $document,
            $request->get('related_type'),
            $request->get('related_id')
        );

        // Load the document with relationships to show the newly created link
        $document->load(['owner', 'creator', 'team', 'relationships.related']);
        
        // Get the newly created relationship for the response
        $newRelationship = \App\Models\DocumentRelationship::where('document_id', $document->id)
            ->where('related_type', $request->get('related_type'))
            ->where('related_id', $request->get('related_id'))
            ->with('related')
            ->first();

        return response()->json([
            'data' => $document,
            'linked_to' => $newRelationship ? $newRelationship->related : null,
            'message' => 'Document linked successfully',
        ]);
    }
}
