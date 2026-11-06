<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Services;

use App\Models\User;
use App\Modules\Attendance\Enums\AttendanceStatus;
use App\Modules\Attendance\Enums\LeaveRequestStatus;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Attendance\Models\LeaveRequest;
use App\Modules\CoreHR\Contracts\EmployeeRepositoryInterface;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\Payroll\Contracts\PayrollComponentRepositoryInterface;
use App\Modules\Payroll\Contracts\PayrollRepositoryInterface;
use App\Modules\Payroll\Contracts\PayslipRepositoryInterface;
use App\Modules\Payroll\Enums\PaymentMethod;
use App\Modules\Payroll\Enums\PayrollComponentType;
use App\Modules\Payroll\Enums\PayrollStatus;
use App\Modules\Payroll\Enums\PayslipStatus;
use App\Modules\Payroll\Jobs\ProcessMonthlyPayrollJob;
use App\Modules\Payroll\Models\EmployeeSalary;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\PayrollComponent;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Models\SalaryStructureComponent;
use App\Modules\RBAC\Enums\PermissionName;
use App\Modules\RBAC\Enums\RoleName;
use App\Modules\RBAC\Services\AuditLogService;
use App\Shared\Exceptions\DomainException;
use App\Shared\Support\MoneyHelper;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    public const STANDARD_WORKING_DAYS = '22';

    public function __construct(
        private readonly PayrollRepositoryInterface $payrollRepository,
        private readonly PayslipRepositoryInterface $payslipRepository,
        private readonly PayrollComponentRepositoryInterface $componentRepository,
        private readonly EmployeeRepositoryInterface $employeeRepository,
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * Generate a monthly payroll batch synchronously inside an atomic database transaction,
     * snapshotting employee master data and computing exact DECIMAL(15, 2) payslips via MoneyHelper.
     */
    public function generatePayroll(
        string $period,
        string $startDate,
        string $endDate,
        int|string $adminId,
        ?string $title = null,
    ): Payroll {
        $normalizedPeriod = trim($period);
        if ($normalizedPeriod === '') {
            throw new DomainException('Payroll period is required.');
        }

        $start = CarbonImmutable::parse($startDate)->startOfDay();
        $end = CarbonImmutable::parse($endDate)->startOfDay();

        if ($end->lt($start)) {
            throw new DomainException('Payroll end date must be on or after the start date.');
        }

        /** @var User $generator */
        $generator = User::query()->findOrFail($adminId);
        $resolvedTitle = $title !== null && trim($title) !== ''
            ? trim($title)
            : 'Payroll '.$start->format('F Y');

        return DB::transaction(function () use (
            $normalizedPeriod,
            $start,
            $end,
            $generator,
            $resolvedTitle,
        ): Payroll {
            $existing = $this->payrollRepository->findByPeriod($normalizedPeriod, lockForUpdate: true);

            if ($existing !== null) {
                if ($existing->isLocked()) {
                    throw new DomainException(
                        "Payroll batch for period [{$normalizedPeriod}] is already {$existing->status->value} and cannot be regenerated.",
                    );
                }

                $this->payslipRepository->purgeDraftsForPayroll($existing->id);

                /** @var Payroll $payroll */
                $payroll = $this->payrollRepository->update($existing, [
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'title' => $resolvedTitle,
                    'status' => PayrollStatus::PROCESSING->value,
                    'total_gross_amount' => '0.00',
                    'total_deduction_amount' => '0.00',
                    'total_net_amount' => '0.00',
                    'generated_by' => $generator->id,
                ]);
            } else {
                /** @var Payroll $payroll */
                $payroll = $this->payrollRepository->create([
                    'payroll_period' => $normalizedPeriod,
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'title' => $resolvedTitle,
                    'status' => PayrollStatus::PROCESSING->value,
                    'total_gross_amount' => '0.00',
                    'total_deduction_amount' => '0.00',
                    'total_net_amount' => '0.00',
                    'generated_by' => $generator->id,
                    'created_by' => $generator->id,
                    'updated_by' => $generator->id,
                ]);
            }

            $activeEmployees = $this->employeeRepository->getActiveForPayroll();

            $batchGross = '0.00';
            $batchDeduction = '0.00';
            $batchNet = '0.00';

            foreach ($activeEmployees as $employee) {
                $payslip = $this->generateEmployeePayslip(
                    payroll: $payroll,
                    employee: $employee,
                    start: $start,
                    end: $end,
                    actorId: $generator->id,
                );

                $employeeGross = MoneyHelper::add($payslip->basic_salary, $payslip->total_allowance);
                $batchGross = MoneyHelper::add($batchGross, $employeeGross);
                $batchDeduction = MoneyHelper::add($batchDeduction, $payslip->total_deduction);
                $batchNet = MoneyHelper::add($batchNet, $payslip->net_salary);
            }

            /** @var Payroll $completed */
            $completed = $this->payrollRepository->update($payroll, [
                'status' => PayrollStatus::GENERATED->value,
                'total_gross_amount' => $batchGross,
                'total_deduction_amount' => $batchDeduction,
                'total_net_amount' => $batchNet,
                'updated_by' => $generator->id,
            ]);

            $this->auditLogService->record(
                event: 'payroll.generated',
                auditable: $completed,
                oldValues: null,
                newValues: [
                    'payroll_period' => $normalizedPeriod,
                    'payslip_count' => $activeEmployees->count(),
                    'total_gross_amount' => $batchGross,
                    'total_deduction_amount' => $batchDeduction,
                    'total_net_amount' => $batchNet,
                ],
                actorId: $generator->id,
            );

            return $completed->load(['generator', 'approver', 'payslips.items']);
        });
    }

    /**
     * Create or mark a payroll batch as PROCESSING and dispatch background calculation job to Redis queue.
     */
    public function dispatchMonthlyPayroll(
        string $period,
        string $startDate,
        string $endDate,
        int|string $adminId,
        ?string $title = null,
    ): Payroll {
        $normalizedPeriod = trim($period);
        $start = CarbonImmutable::parse($startDate)->startOfDay();
        $end = CarbonImmutable::parse($endDate)->startOfDay();

        if ($end->lt($start)) {
            throw new DomainException('Payroll end date must be on or after the start date.');
        }

        /** @var User $generator */
        $generator = User::query()->findOrFail($adminId);
        $resolvedTitle = $title !== null && trim($title) !== ''
            ? trim($title)
            : 'Payroll '.$start->format('F Y');

        $payroll = DB::transaction(function () use ($normalizedPeriod, $start, $end, $generator, $resolvedTitle): Payroll {
            $existing = $this->payrollRepository->findByPeriod($normalizedPeriod, lockForUpdate: true);

            if ($existing !== null) {
                if ($existing->isLocked()) {
                    throw new DomainException(
                        "Payroll batch for period [{$normalizedPeriod}] is already {$existing->status->value} and cannot be regenerated.",
                    );
                }

                /** @var Payroll $updated */
                $updated = $this->payrollRepository->update($existing, [
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'title' => $resolvedTitle,
                    'status' => PayrollStatus::PROCESSING->value,
                    'generated_by' => $generator->id,
                ]);

                return $updated;
            }

            /** @var Payroll $created */
            $created = $this->payrollRepository->create([
                'payroll_period' => $normalizedPeriod,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'title' => $resolvedTitle,
                'status' => PayrollStatus::PROCESSING->value,
                'total_gross_amount' => '0.00',
                'total_deduction_amount' => '0.00',
                'total_net_amount' => '0.00',
                'generated_by' => $generator->id,
            ]);

            return $created;
        });

        ProcessMonthlyPayrollJob::dispatch(
            period: $normalizedPeriod,
            startDate: $start->toDateString(),
            endDate: $end->toDateString(),
            adminId: (int) $generator->id,
            title: $resolvedTitle,
        );

        return $payroll;
    }

    /**
     * Lock and approve a generated payroll batch (Superadmin / Finance Director only),
     * publishing all employee payslips and enforcing permanent immutability.
     */
    public function approvePayroll(int|string $payrollId, int|string $approverId): Payroll
    {
        return DB::transaction(function () use ($payrollId, $approverId): Payroll {
            $payroll = $this->payrollRepository->findWithPayslips($payrollId, lockForUpdate: true);
            if ($payroll === null) {
                throw new DomainException('Payroll batch not found.');
            }

            if ($payroll->isLocked()) {
                throw new DomainException("Payroll batch is already {$payroll->status->value}.");
            }

            if (! in_array($payroll->status, [PayrollStatus::GENERATED, PayrollStatus::DRAFT], true)) {
                throw new DomainException("Cannot approve payroll batch while in {$payroll->status->value} status.");
            }

            /** @var User $approver */
            $approver = User::query()->findOrFail($approverId);
            $this->assertCanApprovePayroll($approver);

            // Publish all payslips BEFORE locking parent payroll status to APPROVED
            $publishedCount = $this->payslipRepository->publishAllForPayroll($payroll->id);
            $approvedAt = CarbonImmutable::now();

            /** @var Payroll $approved */
            $approved = $this->payrollRepository->update($payroll, [
                'status' => PayrollStatus::APPROVED->value,
                'approved_by' => $approver->id,
                'approved_at' => $approvedAt,
                'updated_by' => $approver->id,
            ]);

            $this->auditLogService->record(
                event: 'payroll.approved',
                auditable: $approved,
                oldValues: [
                    'status' => $payroll->status->value,
                ],
                newValues: [
                    'status' => PayrollStatus::APPROVED->value,
                    'approved_by' => $approver->id,
                    'approved_at' => $approvedAt->toIso8601String(),
                    'published_payslips' => $publishedCount,
                ],
                actorId: $approver->id,
            );

            return $approved->load(['generator', 'approver', 'payslips.items']);
        });
    }

    private function generateEmployeePayslip(
        Payroll $payroll,
        Employee $employee,
        CarbonImmutable $start,
        CarbonImmutable $end,
        int|string $actorId,
    ): Payslip {
        /** @var EmployeeSalary|null $activeSalary */
        $activeSalary = $employee->activeSalary;

        $basicSalary = MoneyHelper::normalize(
            (string) ($activeSalary?->base_salary ?? $employee->basic_salary ?? '0.00'),
        );

        $attendanceSummary = $this->buildAttendanceSummary($employee, $start, $end);
        $employeeSnapshot = $this->buildEmployeeSnapshot($employee, $activeSalary);

        $totalAllowance = '0.00';
        $totalDeduction = '0.00';
        /** @var list<array{payroll_component_id: int, name: string, type: string, amount: string}> $lineItems */
        $lineItems = [];
        $hasExplicitAbsentDeduction = false;

        $components = $activeSalary?->salaryStructure?->components ?? collect();

        foreach ($components as $component) {
            if (! $component->is_active) {
                continue;
            }

            if ($component->code === 'ABSENT_DEDUCTION') {
                $hasExplicitAbsentDeduction = true;
            }

            $amount = $this->evaluateComponentAmount(
                component: $component,
                basicSalary: $basicSalary,
                attendanceSummary: $attendanceSummary,
            );

            if (MoneyHelper::compare($amount, '0.00') <= 0) {
                continue;
            }

            if ($component->type === PayrollComponentType::ALLOWANCE) {
                $totalAllowance = MoneyHelper::add($totalAllowance, $amount);
            } else {
                $totalDeduction = MoneyHelper::add($totalDeduction, $amount);
            }

            $lineItems[] = [
                'payroll_component_id' => (int) $component->id,
                'name' => $component->name,
                'type' => $component->type->value,
                'amount' => $amount,
            ];
        }

        // Pro-rata deduction for unexcused absences & approved unpaid leave days
        $unpaidDays = $attendanceSummary['absent_days'] + $attendanceSummary['unpaid_leave_days'];
        if (! $hasExplicitAbsentDeduction && $unpaidDays > 0 && MoneyHelper::compare($basicSalary, '0.00') > 0) {
            $dailyRate = MoneyHelper::div($basicSalary, self::STANDARD_WORKING_DAYS);
            $absentDeductionAmount = MoneyHelper::mul($dailyRate, (string) $unpaidDays);

            if (MoneyHelper::compare($absentDeductionAmount, '0.00') > 0) {
                $absentComponent = $this->resolveSystemAbsentComponent($actorId);
                $totalDeduction = MoneyHelper::add($totalDeduction, $absentDeductionAmount);

                $lineItems[] = [
                    'payroll_component_id' => (int) $absentComponent->id,
                    'name' => $absentComponent->name,
                    'type' => PayrollComponentType::DEDUCTION->value,
                    'amount' => $absentDeductionAmount,
                ];
            }
        }

        $grossSalary = MoneyHelper::add($basicSalary, $totalAllowance);
        $netSalary = MoneyHelper::sub($grossSalary, $totalDeduction);

        if (MoneyHelper::compare($netSalary, '0.00') < 0) {
            $netSalary = '0.00';
        }

        $periodSlug = preg_replace('/[^A-Za-z0-9]/', '', $payroll->payroll_period) ?: 'PERIOD';
        $payslipNumber = sprintf('SLP-%s-%05d', strtoupper($periodSlug), (int) $employee->id);

        /** @var Payslip $payslip */
        $payslip = $this->payslipRepository->create([
            'payroll_id' => $payroll->id,
            'employee_id' => $employee->id,
            'payslip_number' => $payslipNumber,
            'employee_snapshot' => $employeeSnapshot,
            'basic_salary' => $basicSalary,
            'total_allowance' => $totalAllowance,
            'total_deduction' => $totalDeduction,
            'net_salary' => $netSalary,
            'attendance_summary' => $attendanceSummary,
            'status' => PayslipStatus::DRAFT->value,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        foreach ($lineItems as $item) {
            $payslip->items()->create([
                'payroll_component_id' => $item['payroll_component_id'],
                'name' => $item['name'],
                'type' => $item['type'],
                'amount' => $item['amount'],
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
        }

        return $payslip;
    }

    /**
     * @return array{
     *     present_days: int,
     *     late_days: int,
     *     late_minutes: int,
     *     sick_days: int,
     *     leave_days: int,
     *     unpaid_leave_days: int,
     *     absent_days: int,
     *     overtime_minutes: int
     * }
     */
    private function buildAttendanceSummary(Employee $employee, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $attendances = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->get();

        $presentDays = 0;
        $lateDays = 0;
        $lateMinutes = 0;
        $sickDays = 0;
        $leaveDays = 0;
        $absentDays = 0;

        foreach ($attendances as $record) {
            if (in_array($record->status, [AttendanceStatus::PRESENT, AttendanceStatus::LATE], true)) {
                $presentDays++;
            }

            if ($record->status === AttendanceStatus::LATE || $record->late_minutes > 0) {
                $lateDays++;
                $lateMinutes += (int) $record->late_minutes;
            }

            if ($record->status === AttendanceStatus::SICK) {
                $sickDays++;
            }

            if ($record->status === AttendanceStatus::ON_LEAVE) {
                $leaveDays++;
            }

            if ($record->status === AttendanceStatus::ABSENT) {
                $absentDays++;
            }
        }

        // Aggregate approved leave requests overlapping the payroll window
        $approvedLeaves = LeaveRequest::query()
            ->with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('status', LeaveRequestStatus::APPROVED->value)
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->get();

        $unpaidLeaveDays = 0;
        $paidLeaveDaysFromRequests = 0;

        foreach ($approvedLeaves as $leaveRequest) {
            $overlapStart = CarbonImmutable::instance($leaveRequest->start_date)->startOfDay();
            if ($overlapStart->lt($start)) {
                $overlapStart = $start;
            }

            $overlapEnd = CarbonImmutable::instance($leaveRequest->end_date)->startOfDay();
            if ($overlapEnd->gt($end)) {
                $overlapEnd = $end;
            }

            $overlapDays = min(
                (int) $leaveRequest->total_days,
                (int) ($overlapStart->diffInDays($overlapEnd) + 1),
            );

            if ($leaveRequest->leaveType !== null && ! $leaveRequest->leaveType->is_paid) {
                $unpaidLeaveDays += $overlapDays;
            } else {
                $paidLeaveDaysFromRequests += $overlapDays;
            }
        }

        return [
            'present_days' => $presentDays,
            'late_days' => $lateDays,
            'late_minutes' => $lateMinutes,
            'sick_days' => $sickDays,
            'leave_days' => max($leaveDays, $paidLeaveDaysFromRequests),
            'unpaid_leave_days' => $unpaidLeaveDays,
            'absent_days' => $absentDays,
            'overtime_minutes' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEmployeeSnapshot(Employee $employee, ?EmployeeSalary $activeSalary): array
    {
        return [
            'employee_id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'full_name' => $employee->full_name,
            'national_id_number' => $employee->national_id_number,
            'tax_id_number' => $employee->tax_id_number,
            'department_id' => $employee->department_id,
            'department_code' => $employee->department?->code,
            'department_name' => $employee->department?->name,
            'position_id' => $employee->position_id,
            'position_code' => $employee->position?->code,
            'position_title' => $employee->position?->title,
            'position_level' => $employee->position?->level,
            'salary_grade' => $employee->salary_grade,
            'bank_name' => $employee->bank_name,
            'bank_account_number' => $employee->bank_account_number,
            'payment_method' => $activeSalary?->payment_method->value ?? PaymentMethod::BANK_TRANSFER->value,
        ];
    }

    /**
     * Evaluate fixed or dynamic payroll component amount using safe bcmath MoneyHelper arithmetic.
     *
     * @param  array<string, int>  $attendanceSummary
     */
    private function evaluateComponentAmount(
        PayrollComponent $component,
        string $basicSalary,
        array $attendanceSummary,
    ): string {
        /** @var SalaryStructureComponent|null $pivot */
        $pivot = $component->pivot instanceof SalaryStructureComponent ? $component->pivot : null;
        $defaultAmount = MoneyHelper::normalize((string) ($pivot?->default_amount ?? '0.00'));

        if (! $component->is_fixed && $component->formula !== null && trim($component->formula) !== '') {
            $formula = trim($component->formula);

            if (preg_match('/^([a-z_]+)\s*\*\s*(\d+(?:\.\d+)?)$/i', $formula, $matches) === 1) {
                $variable = strtolower($matches[1]);
                $multiplier = $matches[2];

                $baseOperand = match ($variable) {
                    'base_salary', 'basic_salary' => $basicSalary,
                    'present_days' => (string) $attendanceSummary['present_days'],
                    'late_days' => (string) $attendanceSummary['late_days'],
                    'late_minutes' => (string) $attendanceSummary['late_minutes'],
                    'absent_days' => (string) $attendanceSummary['absent_days'],
                    'unpaid_leave_days' => (string) $attendanceSummary['unpaid_leave_days'],
                    'unpaid_days' => (string) ($attendanceSummary['absent_days'] + $attendanceSummary['unpaid_leave_days']),
                    'overtime_minutes' => (string) $attendanceSummary['overtime_minutes'],
                    default => null,
                };

                if ($baseOperand !== null) {
                    return MoneyHelper::mul($baseOperand, $multiplier);
                }
            }
        }

        if (! $component->is_fixed && $component->code === 'LATE_DEDUCTION') {
            return MoneyHelper::mul($defaultAmount, (string) $attendanceSummary['late_days']);
        }

        return $defaultAmount;
    }

    private function resolveSystemAbsentComponent(int|string $actorId): PayrollComponent
    {
        $existing = $this->componentRepository->findByCode('ABSENT_DEDUCTION');
        if ($existing !== null) {
            return $existing;
        }

        /** @var PayrollComponent $created */
        $created = $this->componentRepository->create([
            'code' => 'ABSENT_DEDUCTION',
            'name' => 'Unpaid & Absence Deduction',
            'type' => PayrollComponentType::DEDUCTION->value,
            'is_taxable' => false,
            'is_fixed' => false,
            'formula' => null,
            'is_active' => true,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        return $created;
    }

    private function assertCanApprovePayroll(User $approver): void
    {
        $isAuthorized = $approver->hasRole(RoleName::SUPERADMIN->value)
            || $approver->hasRole('Finance Director')
            || $approver->can(PermissionName::PAYROLL_APPROVE->value);

        if (! $isAuthorized) {
            throw new DomainException('Only Superadmin or Finance Director is authorized to approve payroll batches.');
        }
    }
}
