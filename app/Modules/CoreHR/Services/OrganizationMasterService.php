<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Services;

use App\Modules\CoreHR\Contracts\DepartmentRepositoryInterface;
use App\Modules\CoreHR\Contracts\PositionRepositoryInterface;
use App\Modules\CoreHR\Models\Department;
use App\Modules\CoreHR\Models\Position;
use App\Modules\RBAC\Services\AuditLogService;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

class OrganizationMasterService
{
    public function __construct(
        private readonly DepartmentRepositoryInterface $departmentRepository,
        private readonly PositionRepositoryInterface $positionRepository,
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateDepartment(Department $department, array $payload): Department
    {
        return DB::transaction(function () use ($department, $payload): Department {
            if (
                array_key_exists('parent_id', $payload)
                && $payload['parent_id'] !== null
                && (int) $payload['parent_id'] === (int) $department->id
            ) {
                throw new DomainException('A department cannot be assigned as its own parent.');
            }

            $oldValues = [
                'code' => $department->code,
                'name' => $department->name,
                'parent_id' => $department->parent_id,
                'description' => $department->description,
                'is_active' => $department->is_active,
            ];

            if (isset($payload['code'])) {
                $payload['code'] = strtoupper(trim((string) $payload['code']));
            }

            /** @var Department $updated */
            $updated = $this->departmentRepository->update($department, $payload);

            $this->auditLogService->record(
                event: 'department.updated',
                auditable: $updated,
                oldValues: $oldValues,
                newValues: [
                    'code' => $updated->code,
                    'name' => $updated->name,
                    'parent_id' => $updated->parent_id,
                    'description' => $updated->description,
                    'is_active' => $updated->is_active,
                ],
            );

            return $updated;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updatePosition(Position $position, array $payload): Position
    {
        return DB::transaction(function () use ($position, $payload): Position {
            $oldValues = [
                'department_id' => $position->department_id,
                'code' => $position->code,
                'title' => $position->title,
                'level' => $position->level,
                'description' => $position->description,
                'base_salary_min' => $position->base_salary_min !== null ? (string) $position->base_salary_min : null,
                'base_salary_max' => $position->base_salary_max !== null ? (string) $position->base_salary_max : null,
                'is_active' => $position->is_active,
            ];

            if (isset($payload['code'])) {
                $payload['code'] = strtoupper(trim((string) $payload['code']));
            }

            /** @var Position $updated */
            $updated = $this->positionRepository->update($position, $payload);

            $this->auditLogService->record(
                event: 'position.updated',
                auditable: $updated,
                oldValues: $oldValues,
                newValues: [
                    'department_id' => $updated->department_id,
                    'code' => $updated->code,
                    'title' => $updated->title,
                    'level' => $updated->level,
                    'description' => $updated->description,
                    'base_salary_min' => $updated->base_salary_min !== null ? (string) $updated->base_salary_min : null,
                    'base_salary_max' => $updated->base_salary_max !== null ? (string) $updated->base_salary_max : null,
                    'is_active' => $updated->is_active,
                ],
            );

            return $updated;
        });
    }
}
