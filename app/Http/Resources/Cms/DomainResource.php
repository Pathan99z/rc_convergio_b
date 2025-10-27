<?php

namespace App\Http\Resources\Cms;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DomainResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'ssl_status' => $this->ssl_status,
            'is_primary' => $this->is_primary,
            'is_active' => $this->is_active,
            'settings' => $this->settings,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'url' => $this->url,
            'has_ssl' => $this->hasSsl(),
            'pages_count' => $this->whenCounted('pages'),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}



