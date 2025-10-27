<?php

namespace App\Http\Resources\Cms;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonalizationRuleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'page_id' => $this->page_id,
            'section_id' => $this->section_id,
            'name' => $this->name,
            'description' => $this->description,
            'conditions' => $this->conditions,
            'variant_data' => $this->variant_data,
            'priority' => $this->priority,
            'is_active' => $this->is_active,
            'performance_data' => $this->performance_data,
            'page' => $this->whenLoaded('page', function () {
                return [
                    'id' => $this->page->id,
                    'title' => $this->page->title,
                    'slug' => $this->page->slug,
                    'status' => $this->page->status,
                ];
            }),
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}



