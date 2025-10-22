<?php

namespace App\Http\Resources\Cms;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeoLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'page_id' => $this->page_id,
            'analysis_results' => $this->analysis_results,
            'seo_score' => $this->seo_score,
            'seo_grade' => $this->seo_grade,
            'issues_found' => $this->issues_found,
            'recommendations' => $this->recommendations,
            'keywords_analysis' => $this->keywords_analysis,
            'critical_issues_count' => $this->critical_issues_count,
            'analyzed_at' => $this->analyzed_at->toIso8601String(),
            'analyzer_version' => $this->analyzer_version,
            'page' => $this->whenLoaded('page', function () {
                return [
                    'id' => $this->page->id,
                    'title' => $this->page->title,
                    'slug' => $this->page->slug,
                ];
            }),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}



