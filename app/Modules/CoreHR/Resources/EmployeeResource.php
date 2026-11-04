<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Resources;

use App\Modules\CoreHR\Models\Employee;
use App\Modules\RBAC\Enums\PermissionName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Employee
 */
class EmployeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $actor = $request->user();
        $canViewSensitive = $actor !== null && (
            $actor->can(PermissionName::EMPLOYEE_CREATE->value)
            || $actor->can(PermissionName::EMPLOYEE_UPDATE->value)
            || (int) $this->user_id === (int) $actor->id
        );

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'employee_code' => $this->employee_code,
            'full_name' => $this->full_name ?? $this->user?->name,
            'join_date' => $this->join_date?->toDateString(),
            'status' => $this->status->value,
            'salary_grade' => $this->salary_grade,
            'bank_name' => $this->bank_name,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
                'is_active' => $this->user?->is_active,
            ]),
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $this->department?->id,
                'code' => $this->department?->code,
                'name' => $this->department?->name,
            ]),
            'position' => $this->whenLoaded('position', fn () => [
                'id' => $this->position?->id,
                'code' => $this->position?->code,
                'title' => $this->position?->title,
                'level' => $this->position?->level,
            ]),
            'manager' => $this->whenLoaded('manager', fn () => $this->manager ? [
                'id' => $this->manager->id,
                'employee_code' => $this->manager->employee_code,
                'full_name' => $this->manager->full_name,
            ] : null),
            $this->mergeWhen($canViewSensitive, fn (): array => [
                'national_id_number' => $this->national_id_number,
                'tax_id_number' => $this->tax_id_number,
                'bank_account_number' => $this->bank_account_number,
            ]),
        ];
    }
}
