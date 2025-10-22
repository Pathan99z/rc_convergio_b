<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class DocumentService
{
    /**
     * Upload a document and create a database record.
     */
    public function uploadDocument(UploadedFile $file, array $metadata = []): Document
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? $user->id;
        
        // Generate unique filename
        $filename = $this->generateUniqueFilename($file);
        $filePath = "tenant_{$tenantId}/documents/{$filename}";
        
        // Store the file
        $storedPath = $file->storeAs("tenant_{$tenantId}/documents", $filename, 'local');
        
        // Create document record
        $document = Document::create([
            'tenant_id' => $tenantId,
            'team_id' => $user->team_id,
            'owner_id' => $user->id,
            'title' => $metadata['title'] ?? $file->getClientOriginalName(),
            'file_path' => $storedPath,
            'file_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'visibility' => $metadata['visibility'] ?? 'team',
            'metadata' => $metadata,
            'created_by' => $user->id,
        ]);

        // Create relationship if related_type and related_id are provided
        if (!empty($metadata['related_type']) && !empty($metadata['related_id'])) {
            $this->linkToRecord($document, $metadata['related_type'], $metadata['related_id']);
        }

        // Log the upload activity
        $this->logActivity($document, 'document_uploaded', 'Document uploaded', [
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'file_type' => $file->getMimeType(),
        ]);

        Log::channel('documents')->info('Document uploaded', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'team_id' => $user->team_id,
            'document_id' => $document->id,
            'action' => 'upload',
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
        ]);

        return $document;
    }

    /**
     * Link a document to a related model (deal, quote, contact, etc.).
     */
    public function linkToRecord(Document $document, string $relatedType, int $relatedId): Document
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? $user->id;

        // Standardize related_type format
        $standardizedType = $relatedType;
        if ($relatedType === 'contact') {
            $standardizedType = 'App\\Models\\Contact';
        } elseif ($relatedType === 'deal') {
            $standardizedType = 'App\\Models\\Deal';
        } elseif ($relatedType === 'company') {
            $standardizedType = 'App\\Models\\Company';
        } elseif ($relatedType === 'quote') {
            $standardizedType = 'App\\Models\\Quote';
        }

        // Check if relationship already exists (check both formats to be safe)
        $existingRelationship = \App\Models\DocumentRelationship::where('document_id', $document->id)
            ->where('related_id', $relatedId)
            ->where('tenant_id', $tenantId)
            ->where(function($query) use ($relatedType, $standardizedType) {
                $query->where('related_type', $relatedType)
                      ->orWhere('related_type', $standardizedType);
            })
            ->first();

        if ($existingRelationship) {
            // Relationship already exists, return the document
            return $document->fresh();
        }

        // Create a new relationship record (many-to-many) with standardized type
        $relationship = \App\Models\DocumentRelationship::create([
            'document_id' => $document->id,
            'related_type' => $standardizedType,
            'related_id' => $relatedId,
            'tenant_id' => $tenantId,
            'created_by' => Auth::id() ?? $document->created_by,
        ]);

        // Log the linking activity
        $this->logActivity($document, 'document_linked', "Document linked to {$relatedType}", [
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'relationship_id' => $relationship->id,
        ]);

        Log::channel('documents')->info('Document linked to record', [
            'user_id' => Auth::id(),
            'tenant_id' => $tenantId,
            'document_id' => $document->id,
            'relationship_id' => $relationship->id,
            'action' => 'link',
            'related_type' => $relatedType,
            'related_id' => $relatedId,
        ]);

        return $document->fresh();
    }

    /**
     * Record a document view and increment view count.
     */
    public function recordView(Document $document, User $user): void
    {
        $document->recordView();

        // Log the view activity
        $this->logActivity($document, 'document_viewed', 'Document viewed', [
            'viewer_id' => $user->id,
            'viewer_name' => $user->name,
        ]);

        Log::channel('documents')->info('Document viewed', [
            'user_id' => $user->id,
            'tenant_id' => $document->tenant_id,
            'team_id' => $document->team_id,
            'document_id' => $document->id,
            'action' => 'view',
            'view_count' => $document->view_count,
        ]);
    }

    /**
     * Get analytics for documents.
     */
    public function getAnalytics(int $tenantId, ?int $teamId = null): array
    {
        $query = Document::where('tenant_id', $tenantId);
        
        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        $documents = $query->get();

        return [
            'total_documents' => $documents->count(),
            'total_views' => $documents->sum('view_count'),
            'total_size' => $documents->sum('file_size'),
            'most_viewed' => $documents->sortByDesc('view_count')->take(5)->values(),
            'recent_uploads' => $documents->sortByDesc('created_at')->take(5)->values(),
            'by_file_type' => $documents->groupBy('file_type')->map->count(),
            'by_visibility' => $documents->groupBy('visibility')->map->count(),
            'avg_time_since_upload' => $documents->avg(function ($doc) {
                return $doc->created_at->diffInDays(now());
            }),
        ];
    }

    /**
     * Update document metadata.
     */
    public function updateDocument(Document $document, array $data): Document
    {
        $oldData = $document->toArray();
        
        $document->update($data);

        // Log the update activity
        $this->logActivity($document, 'document_updated', 'Document updated', [
            'changes' => array_diff_assoc($data, $oldData),
        ]);

        Log::channel('documents')->info('Document updated', [
            'user_id' => Auth::id(),
            'tenant_id' => $document->tenant_id,
            'team_id' => $document->team_id,
            'document_id' => $document->id,
            'action' => 'update',
            'changes' => array_diff_assoc($data, $oldData),
        ]);

        return $document->fresh();
    }

    /**
     * Delete a document and its file.
     */
    public function deleteDocument(Document $document): bool
    {
        $documentData = $document->toArray();
        
        // Delete the physical file
        if (Storage::exists($document->file_path)) {
            Storage::delete($document->file_path);
        }

        // Log the deletion activity
        $this->logActivity($document, 'document_deleted', 'Document deleted', [
            'file_name' => $document->title,
            'file_size' => $document->file_size,
        ]);

        Log::channel('documents')->info('Document deleted', [
            'user_id' => Auth::id(),
            'tenant_id' => $document->tenant_id,
            'team_id' => $document->team_id,
            'document_id' => $document->id,
            'action' => 'delete',
            'file_name' => $document->title,
        ]);

        // Soft delete the document
        return $document->delete();
    }

    /**
     * Clean up old files (for cron job).
     */
    public function cleanupOldFiles(int $daysOld = 30): int
    {
        $cutoffDate = now()->subDays($daysOld);
        $deletedCount = 0;

        $oldDocuments = Document::onlyTrashed()
            ->where('deleted_at', '<', $cutoffDate)
            ->get();

        foreach ($oldDocuments as $document) {
            if (Storage::exists($document->file_path)) {
                Storage::delete($document->file_path);
                $deletedCount++;
            }
            
            // Force delete the record
            $document->forceDelete();
        }

        Log::channel('documents')->info('Document cleanup completed', [
            'deleted_files' => $deletedCount,
            'days_old' => $daysOld,
        ]);

        return $deletedCount;
    }

    /**
     * Generate a unique filename for the uploaded file.
     */
    private function generateUniqueFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $basename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $basename = Str::slug($basename);
        
        return $basename . '_' . time() . '_' . Str::random(8) . '.' . $extension;
    }

    /**
     * Log activity for document actions.
     */
    private function logActivity(Document $document, string $type, string $description, array $metadata = []): void
    {
        $userId = Auth::id() ?? $document->created_by;
        
        Activity::create([
            'type' => $type,
            'subject' => "Document: {$document->title}",
            'description' => $description,
            'tenant_id' => $document->tenant_id,
            'owner_id' => $userId,
            'related_type' => 'document',
            'related_id' => $document->id,
            'metadata' => $metadata,
        ]);
    }
}
