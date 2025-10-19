<?php

namespace App\Services;

use App\Models\Activity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ActivityService
{
    /**
     * Log an activity.
     */
    public function log(string $type, array $data = []): void
    {
        try {
            Activity::create([
                'type' => $type,
                'subject' => $data['subject'] ?? $type,
                'description' => $data['description'] ?? $type,
                'metadata' => $data,
                'status' => 'completed',
                'owner_id' => $data['owner_id'] ?? Auth::id(),
                'tenant_id' => $data['tenant_id'] ?? Auth::user()?->tenant_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log activity', [
                'type' => $type,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
