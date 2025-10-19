<?php

namespace App\Http\Resources\Service;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketStatsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'total' => $this->resource['total'],
            'by_status' => [
                'open' => $this->resource['open'],
                'in_progress' => $this->resource['in_progress'],
                'resolved' => $this->resource['resolved'],
                'closed' => $this->resource['closed'],
            ],
            'by_sla' => [
                'on_track' => $this->resource['sla_on_track'],
                'breached' => $this->resource['sla_breached'],
            ],
            'summary' => [
                'active_tickets' => $this->resource['open'] + $this->resource['in_progress'],
                'completed_tickets' => $this->resource['resolved'] + $this->resource['closed'],
                'sla_compliance_rate' => $this->calculateSlaComplianceRate(),
            ],
        ];
    }

    /**
     * Calculate SLA compliance rate
     */
    private function calculateSlaComplianceRate(): float
    {
        $total = $this->resource['sla_on_track'] + $this->resource['sla_breached'];
        
        if ($total === 0) {
            return 0.0;
        }

        return round(($this->resource['sla_on_track'] / $total) * 100, 2);
    }

    /**
     * Get additional data that should be returned with the resource array.
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'version' => '1.0',
                'timestamp' => now()->toISOString(),
                'generated_at' => now()->toISOString(),
            ],
        ];
    }
}
