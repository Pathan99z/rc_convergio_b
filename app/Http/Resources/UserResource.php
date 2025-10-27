<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'organization_name' => $this->organization_name,
            'tenant_id' => $this->tenant_id, // ✅ ADD: Tenant ID for admin visibility
            'team_id' => $this->team_id, // ✅ ADD: Team ID for team management
            'team' => $this->whenLoaded('team', function () { // ✅ ADD: Team relationship
                return [
                    'id' => $this->team->id,
                    'name' => $this->team->name,
                ];
            }),
            'status' => $this->status ?? 'active',
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                    ];
                });
            }),
            'role_names' => $this->whenLoaded('roles', function () {
                return $this->getRoleNames();
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
