<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentDefaultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'default_user_id' => $this->default_user_id,
            'default_team_id' => $this->default_team_id,
            'round_robin_enabled' => $this->round_robin_enabled,
            'enable_automatic_assignment' => $this->enable_automatic_assignment,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'default_user' => $this->whenLoaded('defaultUser', function () {
                return [
                    'id' => $this->defaultUser->id,
                    'name' => $this->defaultUser->name,
                    'email' => $this->defaultUser->email,
                ];
            }),
        ];
    }
}
