<?php

namespace App\Http\Controllers\Api\Help;

use App\Http\Controllers\Controller;
use App\Models\Help\Article;
use App\Models\Help\ArticleAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArticleAttachmentController extends Controller
{
    // Define constant for unauthorized message
    private const UNAUTHORIZED_MESSAGE = 'This action is unauthorized.';

    /**
     * Upload attachment for an article.
     */
    public function uploadAttachment(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        $article = Article::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        if (!$request->user()->can('update', $article)) {
            abort(403, self::UNAUTHORIZED_MESSAGE);
        }

        $request->validate([
            'file' => 'required|file|max:10240|mimes:pdf,doc,docx,txt,jpg,jpeg,png,gif,zip,rar',
        ]);

        try {
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $filename = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '_' . time() . '.' . $extension;
            
            // Store file in tenant-specific directory
            $path = $file->storeAs("help_attachments/{$tenantId}", $filename, 'public');
            
            // Create attachment record
            $attachment = ArticleAttachment::create([
                'tenant_id' => $tenantId,
                'article_id' => $article->id,
                'disk' => 'public',
                'path' => $path,
                'filename' => $originalName,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);

            Log::info('Article attachment uploaded', [
                'attachment_id' => $attachment->id,
                'article_id' => $article->id,
                'tenant_id' => $tenantId,
                'filename' => $originalName,
                'size' => $file->getSize(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Attachment uploaded successfully.',
                'attachment' => [
                    'id' => $attachment->id,
                    'filename' => $attachment->filename,
                    'size' => $attachment->size,
                    'mime_type' => $attachment->mime_type,
                    'url' => Storage::url($attachment->path),
                    'created_at' => $attachment->created_at->toISOString(),
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to upload article attachment', [
                'article_id' => $article->id,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload attachment.',
            ], 500);
        }
    }

    /**
     * Get attachments for an article.
     */
    public function getAttachments(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        $article = Article::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        if (!$request->user()->can('view', $article)) {
            abort(403, self::UNAUTHORIZED_MESSAGE);
        }

        $attachments = ArticleAttachment::where('article_id', $article->id)
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($attachment) {
                return [
                    'id' => $attachment->id,
                    'filename' => $attachment->filename,
                    'size' => $attachment->size,
                    'mime_type' => $attachment->mime_type,
                    'url' => Storage::url($attachment->path),
                    'created_at' => $attachment->created_at->toISOString(),
                ];
            });

        return response()->json([
            'success' => true,
            'attachments' => $attachments,
            'count' => $attachments->count(),
        ]);
    }

    /**
     * Delete an attachment.
     */
    public function deleteAttachment(Request $request, int $id, int $attachmentId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        $article = Article::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        if (!$request->user()->can('update', $article)) {
            abort(403, self::UNAUTHORIZED_MESSAGE);
        }

        $attachment = ArticleAttachment::where('id', $attachmentId)
            ->where('article_id', $article->id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        try {
            // Delete file from storage
            if (Storage::disk($attachment->disk)->exists($attachment->path)) {
                Storage::disk($attachment->disk)->delete($attachment->path);
            }

            // Delete attachment record
            $attachment->delete();

            Log::info('Article attachment deleted', [
                'attachment_id' => $attachmentId,
                'article_id' => $article->id,
                'tenant_id' => $tenantId,
                'filename' => $attachment->filename,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Attachment deleted successfully.',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete article attachment', [
                'attachment_id' => $attachmentId,
                'article_id' => $article->id,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete attachment.',
            ], 500);
        }
    }
}

