<?php

namespace App\Http\Resources\Cms;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ABTestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'page_id' => $this->page_id,
            'variant_a_id' => $this->variant_a_id,
            'variant_b_id' => $this->variant_b_id,
            'traffic_split' => $this->traffic_split,
            'status' => $this->status,
            'performance_data' => $this->performance_data,
            'goals' => $this->goals,
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'min_sample_size' => $this->min_sample_size,
            'confidence_level' => $this->confidence_level,
            'page' => $this->whenLoaded('page', function () {
                return [
                    'id' => $this->page->id,
                    'title' => $this->page->title,
                    'slug' => $this->page->slug,
                ];
            }),
            'variant_a' => $this->whenLoaded('variantA', function () {
                return $this->variantA ? [
                    'id' => $this->variantA->id,
                    'title' => $this->variantA->title,
                    'slug' => $this->variantA->slug,
                ] : null;
            }),
            'variant_b' => $this->whenLoaded('variantB', function () {
                return $this->variantB ? [
                    'id' => $this->variantB->id,
                    'title' => $this->variantB->title,
                    'slug' => $this->variantB->slug,
                ] : null;
            }),
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),
            'visitors_count' => $this->whenCounted('visitors'),
            'is_running' => $this->isRunning(),
            'results' => $this->when($this->status === 'completed', function () {
                return $this->getStatisticalSignificance();
            }),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}



