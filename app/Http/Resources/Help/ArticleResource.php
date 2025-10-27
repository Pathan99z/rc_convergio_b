<?php

namespace App\Http\Resources\Help;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'content' => $this->content,
            'status' => $this->status,
            'published_at' => $this->published_at?->toISOString(),
            'views' => $this->views,
            'helpful_count' => $this->helpful_count,
            'not_helpful_count' => $this->not_helpful_count,
            'helpful_percentage' => $this->helpful_percentage,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'category' => $this->whenLoaded('category', function () {
                return new CategoryResource($this->category);
            }),
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),
            'updater' => $this->whenLoaded('updater', function () {
                return [
                    'id' => $this->updater->id,
                    'name' => $this->updater->name,
                    'email' => $this->updater->email,
                ];
            }),
            'attachments' => $this->whenLoaded('attachments', function () {
                return $this->attachments->map(function ($attachment) {
                    return [
                        'id' => $attachment->id,
                        'filename' => $attachment->filename,
                        'size' => $attachment->size,
                        'mime_type' => $attachment->mime_type,
                        'url' => \Storage::url($attachment->path),
                        'created_at' => $attachment->created_at->toISOString(),
                    ];
                });
            }),
            'links' => [
                'self' => url("/api/help/articles/{$this->slug}?tenant=" . ($this->tenant_id ?? 1)),
                'feedback' => url("/api/help/articles/{$this->id}/feedback?tenant=" . ($this->tenant_id ?? 1)),
            ],
        ];
    }
}
