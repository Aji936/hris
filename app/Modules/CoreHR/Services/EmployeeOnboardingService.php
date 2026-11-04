<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Services;

use App\Models\User;
use App\Modules\CoreHR\Contracts\EmployeeRepositoryInterface;
use App\Modules\CoreHR\Contracts\PositionRepositoryInterface;
use App\Modules\CoreHR\Enums\EmploymentStatus;
use App\Modules\CoreHR\Events\EmployeeOnboarded;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\RBAC\Enums\RoleName;
use App\Modules\RBAC\Services\AuditLogService;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class EmployeeOnboardingService
{
    private const SENSITIVE_FIELDS = [
        'national_id_number',
        'tax_id_number',
        'bank_account_number',
    ];

    public function __construct(
        private readonly EmployeeRepositoryInterface $employeeRepository,
        private readonly PositionRepositoryInterface $positionRepository,
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * Complete transactional employee onboarding:
     * 1. Validates department-position alignment.
     * 2. Creates or links the User account and assigns the Employee role.
     * 3. Persists the Employee record with encrypted PII.
     * 4. Records an immutable audit trail and dispatches EmployeeOnboarded.
     *
     * @param  array<string, mixed>  $payload
     */
    public function onboard(array $payload): Employee
    {
        return DB::transaction(function () use ($payload): Employee {
            $position = $this->positionRepository->findOrFail((int) $payload['position_id']);

            if ((int) $position->department_id !== (int) $payload['department_id']) {
                throw new DomainException('Selected position does not belong to the specified department.');
            }

            $user = $this->resolveOrCreateUser($payload);
            $this->assignDefaultEmployeeRole($user);

            $status = $payload['status'] ?? EmploymentStatus::PROBATION->value;
            if ($status instanceof EmploymentStatus) {
                $status = $status->value;
            }

            /** @var Employee $employee */
            $employee = $this->employeeRepository->create([
                'user_id' => $user->id,
                'employee_code' => $payload['employee_code'],
                'full_name' => $payload['full_name'] ?? $user->name,
                'department_id' => (int) $payload['department_id'],
                'position_id' => (int) $payload['position_id'],
                'manager_id' => isset($payload['manager_id']) ? (int) $payload['manager_id'] : null,
                'join_date' => $payload['join_date'],
                'status' => $status,
                'salary_grade' => $payload['salary_grade'] ?? null,
                'basic_salary' => $payload['basic_salary'] ?? null,
                'national_id_number' => $payload['national_id_number'] ?? null,
                'tax_id_number' => $payload['tax_id_number'] ?? null,
                'bank_account_number' => $payload['bank_account_number'] ?? null,
                'bank_name' => $payload['bank_name'] ?? null,
            ]);

            $this->auditLogService->record(
                event: 'employee.onboarded',
                auditable: $employee,
                oldValues: null,
                newValues: $this->sanitizeAuditPayload([
                    'user_id' => $user->id,
                    'employee_code' => $employee->employee_code,
                    'department_id' => $employee->department_id,
                    'position_id' => $employee->position_id,
                    'manager_id' => $employee->manager_id,
                    'join_date' => $employee->join_date?->toDateString(),
                    'status' => $employee->status->value,
                    'salary_grade' => $employee->salary_grade,
                    'national_id_number' => $payload['national_id_number'] ?? null,
                    'tax_id_number' => $payload['tax_id_number'] ?? null,
                    'bank_account_number' => $payload['bank_account_number'] ?? null,
                    'bank_name' => $employee->bank_name,
                ]),
            );

            event(new EmployeeOnboarded($employee));

            return $employee->load(['user', 'department', 'position', 'manager']);
        });
    }

    /**
     * Update an existing employee record within a transaction and record an audit trail.
     *
     * @param  array<string, mixed>  $payload
     */
    public function update(Employee $employee, array $payload): Employee
    {
        return DB::transaction(function () use ($employee, $payload): Employee {
            $departmentId = isset($payload['department_id'])
                ? (int) $payload['department_id']
                : (int) $employee->department_id;

            $positionId = isset($payload['position_id'])
                ? (int) $payload['position_id']
                : (int) $employee->position_id;

            $position = $this->positionRepository->findOrFail($positionId);

            if ((int) $position->department_id !== $departmentId) {
                throw new DomainException('Selected position does not belong to the specified department.');
            }

            if (array_key_exists('manager_id', $payload) && $payload['manager_id'] !== null && (int) $payload['manager_id'] === (int) $employee->id) {
                throw new DomainException('An employee cannot be assigned as their own manager.');
            }

            $oldSnapshot = $this->sanitizeAuditPayload([
                'employee_code' => $employee->employee_code,
                'department_id' => $employee->department_id,
                'position_id' => $employee->position_id,
                'manager_id' => $employee->manager_id,
                'status' => $employee->status->value,
                'salary_grade' => $employee->salary_grade,
            ]);

            if (isset($payload['status']) && $payload['status'] instanceof EmploymentStatus) {
                $payload['status'] = $payload['status']->value;
            }

            /** @var Employee $updated */
            $updated = $this->employeeRepository->update($employee, $payload);

            if (isset($payload['full_name']) && $updated->user !== null) {
                $updated->user->update(['name' => $payload['full_name']]);
            }

            $this->auditLogService->record(
                event: 'employee.updated',
                auditable: $updated,
                oldValues: $oldSnapshot,
                newValues: $this->sanitizeAuditPayload($payload),
            );

            return $updated->load(['user', 'department', 'position', 'manager']);
        });
    }

    /**
     * Soft-delete an employee record and record an audit log entry.
     */
    public function archive(Employee $employee): bool
    {
        return DB::transaction(function () use ($employee): bool {
            $deleted = $this->employeeRepository->delete($employee);

            $this->auditLogService->record(
                event: 'employee.archived',
                auditable: $employee,
                oldValues: ['status' => $employee->status->value],
                newValues: ['deleted_at' => now()->toIso8601String()],
            );

            return $deleted;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveOrCreateUser(array $payload): User
    {
        if (! empty($payload['user_id'])) {
            /** @var User $existingUser */
            $existingUser = User::query()->findOrFail((int) $payload['user_id']);

            if ($this->employeeRepository->findByUserId($existingUser->id) !== null) {
                throw new DomainException('The specified user account is already linked to an active employee record.');
            }

            return $existingUser;
        }

        /** @var User $newUser */
        $newUser = User::query()->create([
            'name' => (string) ($payload['full_name'] ?? $payload['name']),
            'email' => (string) $payload['email'],
            'password' => (string) ($payload['password'] ?? Str::password(16)),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        return $newUser;
    }

    private function assignDefaultEmployeeRole(User $user): void
    {
        Role::query()->firstOrCreate([
            'name' => RoleName::EMPLOYEE->value,
            'guard_name' => 'web',
        ]);

        if (! $user->hasRole(RoleName::EMPLOYEE->value)) {
            $user->assignRole(RoleName::EMPLOYEE->value);
        }
    }

    /**
     * Mask sensitive PII before storing in JSON audit_logs.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizeAuditPayload(array $data): array
    {
        unset($data['password']);

        foreach (self::SENSITIVE_FIELDS as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $data[$field] = '[ENCRYPTED]';
            }
        }

        return $data;
    }
}
