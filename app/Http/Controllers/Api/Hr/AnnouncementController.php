<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\Announcement;
use App\Services\Hr\AnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AnnouncementController extends Controller
{
    protected AnnouncementService $announcementService;

    public function __construct(AnnouncementService $announcementService)
    {
        $this->announcementService = $announcementService;
    }

    /**
     * Get all announcements with filters.
     * 
     * GET /api/hr/announcements
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Announcement::class);

        // Filter out empty values
        $filters = [];
        if ($request->filled('status')) {
            $filters['status'] = $request->query('status');
        }
        if ($request->filled('category')) {
            $filters['category'] = $request->query('category');
        }
        if ($request->filled('search')) {
            $filters['search'] = $request->query('search');
        }
        if ($request->filled('sortBy')) {
            $filters['sortBy'] = $request->query('sortBy');
        }
        if ($request->filled('sortOrder')) {
            $filters['sortOrder'] = $request->query('sortOrder');
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        $announcements = $this->announcementService->getAnnouncements($filters, $perPage);

        // Add engagement statistics for each announcement
        $announcements->getCollection()->transform(function ($announcement) {
            $announcement->views_count = $announcement->views()->count();
            $announcement->acknowledgments_count = $announcement->acknowledgments()->count();
            $announcement->likes_count = $announcement->likes()->count();
            $announcement->comments_count = $announcement->comments()->count();
            return $announcement;
        });

        return response()->json([
            'success' => true,
            'data' => $announcements->items(),
            'meta' => [
                'current_page' => $announcements->currentPage(),
                'per_page' => $announcements->perPage(),
                'total' => $announcements->total(),
                'last_page' => $announcements->lastPage(),
            ],
        ]);
    }

    /**
     * Create a new announcement.
     * 
     * POST /api/hr/announcements
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Announcement::class);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'category' => 'required|in:birthday,welcome,policy,event,general',
            'message' => 'required|string',
            'attachment' => 'nullable|file|mimes:pdf,doc,docx,txt,jpg,jpeg,png,gif,svg|max:10240', // 10MB max
            'attachment_url' => 'nullable|string|max:500', // Keep for backward compatibility
            'target_audience_type' => 'required|in:all_employees,department_specific,individual',
            'target_departments' => 'required_if:target_audience_type,department_specific|array',
            'target_departments.*' => 'exists:hr_departments,id',
            'target_employee_ids' => 'required_if:target_audience_type,individual|array',
            'target_employee_ids.*' => 'exists:hr_employees,id',
            'is_mandatory' => 'nullable|boolean',
            'priority' => 'nullable|in:low,medium,high',
            'status' => 'nullable|in:draft,published,archived',
            'scheduled_publish_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $validator->validated();
            
            // Handle file upload if provided
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $currentUser = $request->user();
                $tenantId = (int) ($currentUser->tenant_id ?? $currentUser->id);
                
                // Generate unique filename
                $originalName = $file->getClientOriginalName();
                $extension = $file->getClientOriginalExtension();
                $filename = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '_' . time() . '.' . $extension;
                
                // Store file in tenant-specific directory
                $path = $file->storeAs("announcements/{$tenantId}", $filename, 'public');
                
                // Store the storage path (not URL) - the model accessor will convert it to API URL
                $data['attachment_url'] = $path; // e.g., "announcements/1/filename.png"
            }
            
            // Remove 'attachment' from data
            unset($data['attachment']);
            
            // Create announcement (attachment_url already contains the storage path)
            $announcement = $this->announcementService->createAnnouncement($data);

            return response()->json([
                'success' => true,
                'data' => $announcement,
                'message' => 'Announcement created successfully',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get announcement details.
     * 
     * GET /api/hr/announcements/{id}
     */
    public function show(int $id): JsonResponse
    {
        $announcement = Announcement::with(['creator', 'publishedBy', 'views', 'acknowledgments', 'likes', 'comments.employee'])
            ->findOrFail($id);

        $this->authorize('view', $announcement);

        $announcement->views_count = $announcement->views()->count();
        $announcement->acknowledgments_count = $announcement->acknowledgments()->count();
        $announcement->likes_count = $announcement->likes()->count();
        $announcement->comments_count = $announcement->comments()->count();

        return response()->json([
            'success' => true,
            'data' => $announcement,
        ]);
    }

    /**
     * Update an announcement.
     * 
     * PUT /api/hr/announcements/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $announcement = Announcement::findOrFail($id);
        $this->authorize('update', $announcement);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'category' => 'sometimes|required|in:birthday,welcome,policy,event,general',
            'message' => 'sometimes|required|string',
            'attachment' => 'nullable|file|mimes:pdf,doc,docx,txt,jpg,jpeg,png,gif,svg|max:10240', // 10MB max
            'attachment_url' => 'nullable|string|max:500', // Keep for backward compatibility
            'target_audience_type' => 'sometimes|required|in:all_employees,department_specific,individual',
            'target_departments' => 'required_if:target_audience_type,department_specific|array',
            'target_departments.*' => 'exists:hr_departments,id',
            'target_employee_ids' => 'required_if:target_audience_type,individual|array',
            'target_employee_ids.*' => 'exists:hr_employees,id',
            'is_mandatory' => 'nullable|boolean',
            'priority' => 'nullable|in:low,medium,high',
            'status' => 'nullable|in:draft,published,archived',
            'scheduled_publish_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $validator->validated();
            
            // Handle file upload if provided
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $currentUser = $request->user();
                $tenantId = (int) ($currentUser->tenant_id ?? $currentUser->id);
                
                // Delete old file if exists
                if ($announcement->attachment_url) {
                    try {
                        // Get raw path (before accessor converts it)
                        $oldPath = $announcement->getRawOriginal('attachment_url');
                        
                        // If it's a URL, try to extract the path
                        if (str_starts_with($oldPath, 'http') || str_starts_with($oldPath, '/')) {
                            $urlPath = parse_url($oldPath, PHP_URL_PATH);
                            if ($urlPath) {
                                $oldPath = str_replace('/storage/', '', $urlPath);
                            }
                        }
                        
                        // Delete the file if it exists
                        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                            Storage::disk('public')->delete($oldPath);
                        }
                    } catch (\Exception $e) {
                        // Log but don't fail if old file doesn't exist
                        \Log::warning('Failed to delete old announcement attachment', [
                            'announcement_id' => $announcement->id,
                            'path' => $announcement->attachment_url,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                
                // Generate unique filename
                $originalName = $file->getClientOriginalName();
                $extension = $file->getClientOriginalExtension();
                $filename = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '_' . time() . '.' . $extension;
                
                // Store file in tenant-specific directory
                $path = $file->storeAs("announcements/{$tenantId}", $filename, 'public');
                
                // Store the storage path (not URL) - the model accessor will convert it to API URL
                $data['attachment_url'] = $path; // e.g., "announcements/1/filename.png"
            }
            
            // Remove 'attachment' from data
            unset($data['attachment']);
            
            $announcement = $this->announcementService->updateAnnouncement($announcement, $data);

            return response()->json([
                'success' => true,
                'data' => $announcement,
                'message' => 'Announcement updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Delete an announcement.
     * 
     * DELETE /api/hr/announcements/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $announcement = Announcement::findOrFail($id);
        $this->authorize('delete', $announcement);

        try {
            $this->announcementService->deleteAnnouncement($announcement);

            return response()->json([
                'success' => true,
                'message' => 'Announcement deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Publish an announcement.
     * 
     * POST /api/hr/announcements/{id}/publish
     */
    public function publish(int $id): JsonResponse
    {
        $announcement = Announcement::findOrFail($id);
        $this->authorize('publish', $announcement);

        try {
            $announcement = $this->announcementService->publishAnnouncement($announcement);

            return response()->json([
                'success' => true,
                'data' => $announcement,
                'message' => 'Announcement published successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Archive an announcement.
     * 
     * POST /api/hr/announcements/{id}/archive
     */
    public function archive(int $id): JsonResponse
    {
        $announcement = Announcement::findOrFail($id);
        $this->authorize('update', $announcement);

        try {
            $announcement = $this->announcementService->archiveAnnouncement($announcement);

            return response()->json([
                'success' => true,
                'data' => $announcement,
                'message' => 'Announcement archived successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get analytics dashboard.
     * 
     * GET /api/hr/announcements/analytics
     */
    public function analytics(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Announcement::class);

        $filters = [];
        if ($request->filled('start_date')) {
            $filters['start_date'] = $request->query('start_date');
        }
        if ($request->filled('end_date')) {
            $filters['end_date'] = $request->query('end_date');
        }

        $analytics = $this->announcementService->getAnalytics($filters);

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }

    /**
     * Send reminders to pending employees.
     * 
     * POST /api/hr/announcements/{id}/remind
     */
    public function sendReminders(int $id): JsonResponse
    {
        $announcement = Announcement::findOrFail($id);
        $this->authorize('view', $announcement);

        try {
            $result = $this->announcementService->sendReminders($announcement);

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Reminders sent successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Download/preview announcement attachment.
     * 
     * GET /api/hr/announcements/{id}/attachment
     */
    public function attachment(int $id)
    {
        $announcement = Announcement::findOrFail($id);
        $this->authorize('view', $announcement);

        // Get the raw attachment_url value (before accessor converts it)
        $rawUrl = $announcement->getRawOriginal('attachment_url');
        
        if (!$rawUrl) {
            return response()->json([
                'success' => false,
                'message' => 'No attachment found for this announcement',
            ], 404);
        }

        // If it's a storage path (e.g., "announcements/1/filename.png"), use it directly
        $filePath = $rawUrl;
        
        // If it's a URL, try to extract the path
        if (str_starts_with($rawUrl, 'http') || str_starts_with($rawUrl, '/')) {
            // Try to extract path from URL
            $urlPath = parse_url($rawUrl, PHP_URL_PATH);
            if ($urlPath && str_contains($urlPath, 'announcements/')) {
                // Extract the path after /storage/ or directly
                $filePath = str_replace('/storage/', '', $urlPath);
                if (!str_starts_with($filePath, 'announcements/')) {
                    // If still not a path, try to find by filename
                    $filename = basename($urlPath);
                    $tenantId = $announcement->tenant_id;
                    $filePath = "announcements/{$tenantId}/{$filename}";
                }
            } else {
                // Fallback: try to find file by announcement creation time
                $tenantId = $announcement->tenant_id;
                $storagePath = "announcements/{$tenantId}";
                $files = Storage::disk('public')->files($storagePath);
                
                if (empty($files)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Attachment file not found',
                    ], 404);
                }
                
                // Find file by creation time
                $announcementTime = $announcement->created_at->timestamp;
                foreach ($files as $file) {
                    $fileTime = Storage::disk('public')->lastModified($file);
                    if (abs($fileTime - $announcementTime) < 5) {
                        $filePath = $file;
                        break;
                    }
                }
                
                // If still not found, use most recent
                if (!Storage::disk('public')->exists($filePath)) {
                    usort($files, function($a, $b) {
                        return Storage::disk('public')->lastModified($b) - Storage::disk('public')->lastModified($a);
                    });
                    $filePath = $files[0];
                }
            }
        }

        // Check if file exists
        if (!Storage::disk('public')->exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment file not found',
            ], 404);
        }

        // Get file content and MIME type
        $fileContent = Storage::disk('public')->get($filePath);
        $mimeType = Storage::disk('public')->mimeType($filePath);
        $filename = basename($filePath);

        // Return file with appropriate headers
        return response($fileContent)
            ->header('Content-Type', $mimeType)
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}

