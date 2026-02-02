<?php

namespace App\Http\Resources\Hr;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class EmployeeResource extends JsonResource
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
            'employee_id' => $this->employee_id,
            // Personal Information
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'preferred_name' => $this->preferred_name,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'gender' => $this->gender,
            'nationality' => $this->nationality,
            'marital_status' => $this->marital_status,
            'id_number' => $this->id_number,
            'passport_number' => $this->passport_number,
            // Contact Details
            'work_email' => $this->work_email,
            'personal_email' => $this->personal_email,
            'phone_number' => $this->phone_number,
            'work_phone' => $this->work_phone,
            'office_address' => $this->office_address,
            'address' => $this->address,
            'emergency_contact' => $this->emergency_contact,
            // Job Information
            'job_title' => $this->job_title,
            'department' => $this->department, // Keep for backward compatibility
            'department_id' => $this->department_id,
            'department_detail' => $this->when(
                $this->relationLoaded('department') && $this->getRelation('department') !== null,
                function () {
                    $department = $this->getRelation('department');
                    return [
                        'id' => $department->id,
                        'name' => $department->name,
                        'code' => $department->code,
                    ];
                }
            ),
            'designation_id' => $this->designation_id,
            'designation_detail' => $this->when(
                $this->relationLoaded('designation') && $this->getRelation('designation') !== null,
                function () {
                    $designation = $this->getRelation('designation');
                    return [
                        'id' => $designation->id,
                        'name' => $designation->name,
                        'code' => $designation->code,
                        'is_manager' => $designation->is_manager ?? false,
                    ];
                }
            ),
            'employment_type' => $this->employment_type,
            'employment_status' => $this->employment_status,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'work_schedule' => $this->work_schedule,
            'probation_end_date' => $this->probation_end_date?->toDateString(),
            'contract_end_date' => $this->contract_end_date?->toDateString(),
            'manager_id' => $this->manager_id,
            'manager' => $this->whenLoaded('manager', function () {
                return [
                    'id' => $this->manager->id,
                    'employee_id' => $this->manager->employee_id,
                    'full_name' => $this->manager->full_name,
                    'job_title' => $this->manager->job_title,
                    'department' => $this->manager->department,
                ];
            }),
            'team_id' => $this->team_id,
            'team' => $this->whenLoaded('team', function () {
                return [
                    'id' => $this->team->id,
                    'name' => $this->team->name,
                ];
            }),
            // Profile Picture
            'profile_picture_id' => $this->profile_picture_id,
            'profile_picture' => $this->when(
                $this->relationLoaded('profilePicture') && $this->getRelation('profilePicture') !== null,
                function () {
                    $profilePicture = $this->getRelation('profilePicture');
                    return [
                        'id' => $profilePicture->id,
                        'url' => $profilePicture->id ? URL::signedRoute('documents.profile-picture', ['id' => $profilePicture->id], now()->addDays(30)) : null,
                    ];
                }
            ),
            // User Account
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            'archived_at' => $this->archived_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

