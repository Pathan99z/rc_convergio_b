<?php

namespace App\Http\Resources\Cms;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PageResource extends JsonResource
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
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_keywords' => $this->meta_keywords,
            'status' => $this->status,
            'json_content' => $this->json_content,
            'template' => new TemplateResource($this->whenLoaded('template')),
            'domain' => new DomainResource($this->whenLoaded('domain')),
            'language' => new LanguageResource($this->whenLoaded('language')),
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
            'published_at' => $this->published_at?->toIso8601String(),
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'seo_data' => $this->seo_data,
            'view_count' => $this->view_count,
            'settings' => $this->settings,
            'url' => $this->url,
            'is_published' => $this->isPublished(),
            'is_scheduled' => $this->isScheduled(),
            'personalization_rules' => PersonalizationRuleResource::collection($this->whenLoaded('personalizationRules')),
            'seo_logs' => SeoLogResource::collection($this->whenLoaded('seoLogs')),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}



