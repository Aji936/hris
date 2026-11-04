<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Services;

use App\Modules\CoreHR\Contracts\EmployeeRepositoryInterface;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\Payroll\Contracts\EmployeeSalaryRepositoryInterface;
use App\Modules\Payroll\Contracts\PayrollComponentRepositoryInterface;
use App\Modules\Payroll\Contracts\SalaryStructureRepositoryInterface;
use App\Modules\Payroll\Enums\PaymentMethod;
use App\Modules\Payroll\Enums\PayrollComponentType;
use App\Modules\Payroll\Models\EmployeeSalary;
use App\Modules\Payroll\Models\PayrollComponent;
use App\Modules\Payroll\Models\SalaryStructure;
use App\Modules\RBAC\Services\AuditLogService;
use App\Shared\Exceptions\DomainException;
use App\Shared\Support\MoneyHelper;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SalaryAssignmentService
{
    public function __construct(
        private readonly PayrollComponentRepositoryInterface $componentRepository,
        private readonly SalaryStructureRepositoryInterface $structureRepository,
        private readonly EmployeeSalaryRepositoryInterface $employeeSalaryRepository,
        private readonly EmployeeRepositoryInterface $employeeRepository,
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * Create a reusable payroll component (Allowance or Deduction).
     *
     * @param  array<string, mixed>  $data
     */
    public function createComponent(array $data): PayrollComponent
    {
        $code = strtoupper(trim((string) $data['code']));
        if ($this->componentRepository->findByCode($code) !== null) {
            throw new DomainException("Payroll component with code [{$code}] already exists.");
        }

        $type = $data['type'] instanceof PayrollComponentType
            ? $data['type']
            : PayrollComponentType::from(strtoupper((string) $data['type']));

        return DB::transaction(function () use ($data, $code, $type): PayrollComponent {
            /** @var PayrollComponent $component */
            $component = $this->componentRepository->create([
                'code' => $code,
                'name' => trim((string) $data['name']),
                'type' => $type->value,
                'is_taxable' => (bool) ($data['is_taxable'] ?? true),
                'is_fixed' => (bool) ($data['is_fixed'] ?? true),
                'formula' => isset($data['formula']) && trim((string) $data['formula']) !== ''
                    ? trim((string) $data['formula'])
                    : null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            $this->auditLogService->record(
                event: 'payroll.component_created',
                auditable: $component,
                oldValues: null,
                newValues: [
                    'code' => $component->code,
                    'type' => $component->type->value,
                    'is_taxable' => $component->is_taxable,
                    'is_fixed' => $component->is_fixed,
                ],
            );

            return $component;
        });
    }

    /**
     * Create a salary structure and attach default component amounts using exact DECIMAL(15, 2) strings.
     *
     * @param  array<string, mixed>  $data
     */
    public function createStructure(array $data): SalaryStructure
    {
        $syncPayload = $this->normalizeComponentPayload($data['components'] ?? []);

        return DB::transaction(function () use ($data, $syncPayload): SalaryStructure {
            /** @var SalaryStructure $structure */
            $structure = $this->structureRepository->create([
                'name' => trim((string) $data['name']),
                'description' => $data['description'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            if ($syncPayload !== []) {
                $structure = $this->structureRepository->syncComponents($structure, $syncPayload);
            } else {
                $structure->load('components');
            }

            $this->auditLogService->record(
                event: 'payroll.salary_structure_created',
                auditable: $structure,
                oldValues: null,
                newValues: [
                    'name' => $structure->name,
                    'component_count' => count($syncPayload),
                ],
            );

            return $structure;
        });
    }

    /**
     * Assign a salary structure and base salary to an employee, automatically archiving
     * any previously active salary record when applying a salary increment or adjustment.
     *
     * @param  array<string, mixed>  $data
     */
    public function assignSalary(int|string $employeeId, array $data): EmployeeSalary
    {
        $normalizedBaseSalary = $this->normalizeNonNegativeAmount($data['base_salary'] ?? null, 'base_salary');
        $effectiveDate = CarbonImmutable::parse((string) $data['effective_date'])->startOfDay();

        $paymentMethod = isset($data['payment_method']) && $data['payment_method'] instanceof PaymentMethod
            ? $data['payment_method']
            : PaymentMethod::from((string) ($data['payment_method'] ?? PaymentMethod::BANK_TRANSFER->value));

        return DB::transaction(function () use (
            $employeeId,
            $data,
            $normalizedBaseSalary,
            $effectiveDate,
            $paymentMethod,
        ): EmployeeSalary {
            /** @var Employee $employee */
            $employee = $this->employeeRepository->findOrFail($employeeId);

            /** @var SalaryStructure $structure */
            $structure = $this->structureRepository->findOrFail($data['salary_structure_id']);
            if (! $structure->is_active) {
                throw new DomainException("Salary structure '{$structure->name}' is inactive and cannot be assigned.");
            }

            $currentActive = $this->employeeSalaryRepository->findActiveForEmployee(
                employeeId: $employee->id,
                lockForUpdate: true,
            );

            $oldValues = null;

            if ($currentActive !== null) {
                $previousEffectiveDate = CarbonImmutable::instance($currentActive->effective_date)->startOfDay();
                if ($effectiveDate->lt($previousEffectiveDate)) {
                    throw new DomainException(
                        "New salary effective date ({$effectiveDate->toDateString()}) cannot be earlier than current active salary date ({$previousEffectiveDate->toDateString()}).",
                    );
                }

                $oldValues = [
                    'archived_salary_id' => $currentActive->id,
                    'previous_salary_structure_id' => $currentActive->salary_structure_id,
                    'previous_base_salary' => (string) $currentActive->base_salary,
                    'previous_effective_date' => $previousEffectiveDate->toDateString(),
                ];

                $this->employeeSalaryRepository->archiveActiveForEmployee($employee->id);
            }

            /** @var EmployeeSalary $assigned */
            $assigned = $this->employeeSalaryRepository->create([
                'employee_id' => $employee->id,
                'salary_structure_id' => $structure->id,
                'effective_date' => $effectiveDate->toDateString(),
                'base_salary' => $normalizedBaseSalary,
                'payment_method' => $paymentMethod->value,
                'is_active' => true,
            ]);

            // Keep Core HR employee basic_salary snapshot synchronized with active payroll assignment
            $this->employeeRepository->update($employee, [
                'basic_salary' => $normalizedBaseSalary,
            ]);

            $this->auditLogService->record(
                event: $oldValues === null ? 'payroll.salary_assigned' : 'payroll.salary_incremented',
                auditable: $assigned,
                oldValues: $oldValues,
                newValues: [
                    'employee_id' => $employee->id,
                    'salary_structure_id' => $structure->id,
                    'effective_date' => $effectiveDate->toDateString(),
                    'base_salary' => $normalizedBaseSalary,
                    'payment_method' => $paymentMethod->value,
                ],
            );

            return $assigned->load(['employee', 'salaryStructure.components']);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     * @return array<int|string, array{default_amount: string}>
     */
    private function normalizeComponentPayload(array $components): array
    {
        $syncPayload = [];

        foreach ($components as $item) {
            $componentId = $item['payroll_component_id'] ?? null;
            if ($componentId === null) {
                throw new DomainException('Each structure component entry must specify payroll_component_id.');
            }

            $this->componentRepository->findOrFail($componentId);
            $normalizedAmount = $this->normalizeNonNegativeAmount(
                $item['default_amount'] ?? '0.00',
                'default_amount',
            );

            $syncPayload[$componentId] = [
                'default_amount' => $normalizedAmount,
            ];
        }

        return $syncPayload;
    }

    private function normalizeNonNegativeAmount(mixed $rawAmount, string $fieldName): string
    {
        if ($rawAmount === null || (! is_string($rawAmount) && ! is_int($rawAmount) && ! is_float($rawAmount))) {
            throw new DomainException("Field [{$fieldName}] must be a valid non-negative decimal amount.");
        }

        try {
            $normalized = MoneyHelper::normalize((string) $rawAmount);
        } catch (InvalidArgumentException) {
            throw new DomainException("Field [{$fieldName}] must be a valid numeric value.");
        }

        if (MoneyHelper::compare($normalized, '0.00') < 0) {
            throw new DomainException("Field [{$fieldName}] cannot be negative.");
        }

        if (MoneyHelper::compare($normalized, '9999999999999.99') > 0) {
            throw new DomainException("Field [{$fieldName}] exceeds maximum DECIMAL(15, 2) precision.");
        }

        return $normalized;
    }
}
