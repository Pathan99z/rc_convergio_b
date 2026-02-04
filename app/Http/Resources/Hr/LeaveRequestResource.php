<?php

namespace App\Http\Resources\Hr;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
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
            'employee' => $this->whenLoaded('employee', function () {
                return [
                    'id' => $this->employee->id,
                    'employee_id' => $this->employee->employee_id,
                    'full_name' => $this->employee->full_name,
                ];
            }),
            'leave_type' => $this->whenLoaded('leaveType', function () {
                return [
                    'id' => $this->leaveType->id,
                    'name' => $this->leaveType->name,
                    'code' => $this->leaveType->code,
                ];
            }),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'days_requested' => $this->days_requested,
            'reason' => $this->reason,
            'status' => $this->status,
            'approved_by' => $this->whenLoaded('approvedBy', function () {
                return [
                    'id' => $this->approvedBy->id,
                    'name' => $this->approvedBy->name,
                ];
            }),
            'approved_at' => $this->approved_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}


