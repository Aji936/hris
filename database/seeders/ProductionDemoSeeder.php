<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Attendance\Enums\AttendanceStatus;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Attendance\Models\LeaveBalance;
use App\Modules\Attendance\Models\LeaveType;
use App\Modules\Attendance\Models\Shift;
use App\Modules\CoreHR\Enums\EmploymentStatus;
use App\Modules\CoreHR\Models\Department;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\Position;
use App\Modules\Payroll\Contracts\PayrollComponentRepositoryInterface;
use App\Modules\Payroll\Contracts\PayrollRepositoryInterface;
use App\Modules\Payroll\Enums\PaymentMethod;
use App\Modules\Payroll\Enums\PayrollComponentType;
use App\Modules\Payroll\Models\SalaryStructure;
use App\Modules\Payroll\Services\PayrollService;
use App\Modules\Payroll\Services\SalaryAssignmentService;
use App\Modules\RBAC\Enums\RoleName;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class ProductionDemoSeeder extends Seeder
{
    public function run(): void
    {
        /** @var SalaryAssignmentService $salaryAssignmentService */
        $salaryAssignmentService = app(SalaryAssignmentService::class);
        /** @var PayrollComponentRepositoryInterface $componentRepository */
        $componentRepository = app(PayrollComponentRepositoryInterface::class);
        /** @var PayrollRepositoryInterface $payrollRepository */
        $payrollRepository = app(PayrollRepositoryInterface::class);
        /** @var PayrollService $payrollService */
        $payrollService = app(PayrollService::class);

        // 1. Accounts: 1 Superadmin & 1 HR Admin
        $superadmin = $this->upsertUser(
            email: 'admin@hris.local',
            name: 'System Superadmin',
            role: RoleName::SUPERADMIN,
        );

        $hrAdmin = $this->upsertUser(
            email: 'hr@hris.local',
            name: 'Nadia Putri (HR Admin)',
            role: RoleName::HR_ADMIN,
        );

        // 2. Hierarchical Organization Structure: 4 Departments & 8 Positions
        $opsDept = Department::query()->updateOrCreate(
            ['code' => 'OPS'],
            [
                'parent_id' => null,
                'name' => 'Operations',
                'description' => 'Corporate operations, facilities, and business continuity.',
                'is_active' => true,
                'created_by' => $superadmin->id,
                'updated_by' => $superadmin->id,
            ],
        );

        $engDept = Department::query()->updateOrCreate(
            ['code' => 'ENG'],
            [
                'parent_id' => $opsDept->id,
                'name' => 'Engineering',
                'description' => 'Software engineering, architecture, and quality assurance.',
                'is_active' => true,
                'created_by' => $superadmin->id,
                'updated_by' => $superadmin->id,
            ],
        );

        $hrDept = Department::query()->updateOrCreate(
            ['code' => 'HR'],
            [
                'parent_id' => $opsDept->id,
                'name' => 'HR',
                'description' => 'People operations, talent acquisition, and organizational culture.',
                'is_active' => true,
                'created_by' => $superadmin->id,
                'updated_by' => $superadmin->id,
            ],
        );

        $finDept = Department::query()->updateOrCreate(
            ['code' => 'FIN'],
            [
                'parent_id' => $opsDept->id,
                'name' => 'Finance',
                'description' => 'Financial planning, accounting, tax compliance, and payroll.',
                'is_active' => true,
                'created_by' => $superadmin->id,
                'updated_by' => $superadmin->id,
            ],
        );

        $positionsSpec = [
            ['code' => 'POS-ENG-MGR', 'department_id' => $engDept->id, 'title' => 'Engineering Manager', 'level' => 'M1', 'min' => '25000000.00', 'max' => '35000000.00'],
            ['code' => 'POS-ENG-SWE', 'department_id' => $engDept->id, 'title' => 'Senior Software Engineer', 'level' => 'S3', 'min' => '18000000.00', 'max' => '26000000.00'],
            ['code' => 'POS-ENG-QA', 'department_id' => $engDept->id, 'title' => 'QA Automation Engineer', 'level' => 'S2', 'min' => '13000000.00', 'max' => '18000000.00'],
            ['code' => 'POS-HR-SPEC', 'department_id' => $hrDept->id, 'title' => 'HR Operations Specialist', 'level' => 'S2', 'min' => '12000000.00', 'max' => '17000000.00'],
            ['code' => 'POS-HR-TA', 'department_id' => $hrDept->id, 'title' => 'Talent Acquisition Partner', 'level' => 'S2', 'min' => '11000000.00', 'max' => '16000000.00'],
            ['code' => 'POS-FIN-ACC', 'department_id' => $finDept->id, 'title' => 'Payroll & Tax Accountant', 'level' => 'S3', 'min' => '14000000.00', 'max' => '20000000.00'],
            ['code' => 'POS-OPS-MGR', 'department_id' => $opsDept->id, 'title' => 'Operations Manager', 'level' => 'M1', 'min' => '22000000.00', 'max' => '30000000.00'],
            ['code' => 'POS-OPS-COOR', 'department_id' => $opsDept->id, 'title' => 'Operations Coordinator', 'level' => 'S1', 'min' => '9500000.00', 'max' => '13500000.00'],
        ];

        /** @var array<string, Position> $positions */
        $positions = [];
        foreach ($positionsSpec as $spec) {
            $positions[$spec['code']] = Position::query()->updateOrCreate(
                ['code' => $spec['code']],
                [
                    'department_id' => $spec['department_id'],
                    'title' => $spec['title'],
                    'level' => $spec['level'],
                    'description' => $spec['title'].' role within organization.',
                    'base_salary_min' => $spec['min'],
                    'base_salary_max' => $spec['max'],
                    'is_active' => true,
                    'created_by' => $superadmin->id,
                    'updated_by' => $superadmin->id,
                ],
            );
        }

        // 3. Standard Head Office Shift & Leave Types
        $regularShift = Shift::query()->updateOrCreate(
            ['name' => 'Head Office Regular Shift'],
            [
                'start_time' => '09:00:00',
                'end_time' => '17:00:00',
                'late_grace_period_minutes' => 15,
                'is_active' => true,
                'created_by' => $hrAdmin->id,
                'updated_by' => $hrAdmin->id,
            ],
        );

        $annualLeave = LeaveType::query()->updateOrCreate(
            ['code' => 'ANNUAL'],
            [
                'name' => 'Annual Leave',
                'default_quota_days' => 12,
                'is_paid' => true,
                'requires_attachment' => false,
                'is_active' => true,
                'created_by' => $hrAdmin->id,
                'updated_by' => $hrAdmin->id,
            ],
        );

        $sickLeave = LeaveType::query()->updateOrCreate(
            ['code' => 'SICK'],
            [
                'name' => 'Sick Leave',
                'default_quota_days' => 14,
                'is_paid' => true,
                'requires_attachment' => true,
                'is_active' => true,
                'created_by' => $hrAdmin->id,
                'updated_by' => $hrAdmin->id,
            ],
        );

        LeaveType::query()->updateOrCreate(
            ['code' => 'UNPAID'],
            [
                'name' => 'Unpaid Personal Leave',
                'default_quota_days' => 30,
                'is_paid' => false,
                'requires_attachment' => false,
                'is_active' => true,
                'created_by' => $hrAdmin->id,
                'updated_by' => $hrAdmin->id,
            ],
        );

        // 4. Payroll Master Components & Standard Salary Structure
        $mealComponent = $componentRepository->findByCode('MEAL_ALLOWANCE')
            ?? $salaryAssignmentService->createComponent([
                'code' => 'MEAL_ALLOWANCE',
                'name' => 'Meal & Transport Allowance',
                'type' => PayrollComponentType::ALLOWANCE->value,
                'is_taxable' => true,
                'is_fixed' => true,
                'is_active' => true,
            ]);

        $bpjsTkComponent = $componentRepository->findByCode('BPJS_TK')
            ?? $salaryAssignmentService->createComponent([
                'code' => 'BPJS_TK',
                'name' => 'BPJS Ketenagakerjaan (Employee 2%)',
                'type' => PayrollComponentType::DEDUCTION->value,
                'is_taxable' => false,
                'is_fixed' => false,
                'formula' => 'basic_salary * 0.02',
                'is_active' => true,
            ]);

        $bpjsKesComponent = $componentRepository->findByCode('BPJS_KES')
            ?? $salaryAssignmentService->createComponent([
                'code' => 'BPJS_KES',
                'name' => 'BPJS Kesehatan (Employee 1%)',
                'type' => PayrollComponentType::DEDUCTION->value,
                'is_taxable' => false,
                'is_fixed' => false,
                'formula' => 'basic_salary * 0.01',
                'is_active' => true,
            ]);

        $lateDeductionComponent = $componentRepository->findByCode('LATE_DEDUCTION')
            ?? $salaryAssignmentService->createComponent([
                'code' => 'LATE_DEDUCTION',
                'name' => 'Late Attendance Penalty',
                'type' => PayrollComponentType::DEDUCTION->value,
                'is_taxable' => false,
                'is_fixed' => false,
                'is_active' => true,
            ]);

        $standardStructure = SalaryStructure::query()->where('name', 'Corporate Standard Package 2026')->first()
            ?? $salaryAssignmentService->createStructure([
                'name' => 'Corporate Standard Package 2026',
                'description' => 'Standard monthly compensation structure with meal allowance, BPJS, and late penalty.',
                'components' => [
                    ['payroll_component_id' => $mealComponent->id, 'default_amount' => '1500000.00'],
                    ['payroll_component_id' => $bpjsTkComponent->id, 'default_amount' => '0.00'],
                    ['payroll_component_id' => $bpjsKesComponent->id, 'default_amount' => '0.00'],
                    ['payroll_component_id' => $lateDeductionComponent->id, 'default_amount' => '50000.00'],
                ],
            ]);

        // 5. Seed 2 Managers (with Employee records so subordinate manager_id hierarchy works)
        $engManagerUser = $this->upsertUser(
            email: 'manager.eng@hris.local',
            name: 'Arif Pratama',
            role: RoleName::MANAGER,
        );
        $engManagerEmployee = $this->upsertEmployee(
            user: $engManagerUser,
            code: 'EMP-MGR-001',
            departmentId: $engDept->id,
            positionId: $positions['POS-ENG-MGR']->id,
            shiftId: $regularShift->id,
            managerId: null,
            grade: 'M1',
            baseSalary: '28000000.00',
            nik: '3171011505880001',
            npwp: '091234567890000',
            bankAccount: '1230098765401',
            bankName: 'Bank Mandiri',
            actorId: $hrAdmin->id,
        );

        $opsManagerUser = $this->upsertUser(
            email: 'manager.ops@hris.local',
            name: 'Maya Kusuma',
            role: RoleName::MANAGER,
        );
        $opsManagerEmployee = $this->upsertEmployee(
            user: $opsManagerUser,
            code: 'EMP-MGR-002',
            departmentId: $opsDept->id,
            positionId: $positions['POS-OPS-MGR']->id,
            shiftId: $regularShift->id,
            managerId: null,
            grade: 'M1',
            baseSalary: '25000000.00',
            nik: '3172022008890002',
            npwp: '091234567890001',
            bankAccount: '1230098765402',
            bankName: 'BCA',
            actorId: $hrAdmin->id,
        );

        // 6. Seed 10 Active Employees with Encrypted PII (NIK, NPWP, Bank Account)
        $employeesSpec = [
            ['idx' => 1, 'name' => 'Budi Santoso', 'dept' => $engDept->id, 'pos' => 'POS-ENG-SWE', 'mgr' => $engManagerEmployee->id, 'grade' => 'S3', 'salary' => '20000000.00', 'bank' => 'BCA'],
            ['idx' => 2, 'name' => 'Siti Rahmawati', 'dept' => $engDept->id, 'pos' => 'POS-ENG-SWE', 'mgr' => $engManagerEmployee->id, 'grade' => 'S3', 'salary' => '19500000.00', 'bank' => 'Bank Mandiri'],
            ['idx' => 3, 'name' => 'Dimas Saputra', 'dept' => $engDept->id, 'pos' => 'POS-ENG-QA', 'mgr' => $engManagerEmployee->id, 'grade' => 'S2', 'salary' => '15000000.00', 'bank' => 'BNI'],
            ['idx' => 4, 'name' => 'Rina Kartika', 'dept' => $engDept->id, 'pos' => 'POS-ENG-QA', 'mgr' => $engManagerEmployee->id, 'grade' => 'S2', 'salary' => '14500000.00', 'bank' => 'BCA'],
            ['idx' => 5, 'name' => 'Fajar Nugroho', 'dept' => $hrDept->id, 'pos' => 'POS-HR-SPEC', 'mgr' => $opsManagerEmployee->id, 'grade' => 'S2', 'salary' => '13500000.00', 'bank' => 'BRI'],
            ['idx' => 6, 'name' => 'Lestari Wulandari', 'dept' => $hrDept->id, 'pos' => 'POS-HR-TA', 'mgr' => $opsManagerEmployee->id, 'grade' => 'S2', 'salary' => '12500000.00', 'bank' => 'BCA'],
            ['idx' => 7, 'name' => 'Hendra Wijaya', 'dept' => $finDept->id, 'pos' => 'POS-FIN-ACC', 'mgr' => $opsManagerEmployee->id, 'grade' => 'S3', 'salary' => '16500000.00', 'bank' => 'Bank Mandiri'],
            ['idx' => 8, 'name' => 'Putri Maharani', 'dept' => $finDept->id, 'pos' => 'POS-FIN-ACC', 'mgr' => $opsManagerEmployee->id, 'grade' => 'S3', 'salary' => '16000000.00', 'bank' => 'BNI'],
            ['idx' => 9, 'name' => 'Rizky Ramadhan', 'dept' => $opsDept->id, 'pos' => 'POS-OPS-COOR', 'mgr' => $opsManagerEmployee->id, 'grade' => 'S1', 'salary' => '11000000.00', 'bank' => 'BCA'],
            ['idx' => 10, 'name' => 'Anisa Permata', 'dept' => $opsDept->id, 'pos' => 'POS-OPS-COOR', 'mgr' => $opsManagerEmployee->id, 'grade' => 'S1', 'salary' => '10500000.00', 'bank' => 'Bank Mandiri'],
        ];

        /** @var list<Employee> $allSeededEmployees */
        $allSeededEmployees = [$engManagerEmployee, $opsManagerEmployee];

        foreach ($employeesSpec as $row) {
            $index = $row['idx'];
            $empUser = $this->upsertUser(
                email: sprintf('employee%d@hris.local', $index),
                name: $row['name'],
                role: RoleName::EMPLOYEE,
            );

            $empRecord = $this->upsertEmployee(
                user: $empUser,
                code: sprintf('EMP-2026-%04d', $index),
                departmentId: $row['dept'],
                positionId: $positions[$row['pos']]->id,
                shiftId: $regularShift->id,
                managerId: $row['mgr'],
                grade: $row['grade'],
                baseSalary: $row['salary'],
                nik: sprintf('317305100195%04d', $index),
                npwp: sprintf('08987654321%04d', $index),
                bankAccount: sprintf('884001239%04d', $index),
                bankName: $row['bank'],
                actorId: $hrAdmin->id,
            );

            $allSeededEmployees[] = $empRecord;
        }

        // 7. Assign Active Salaries, 2026 Leave Balances & 30-Day Attendance History
        $today = CarbonImmutable::now()->startOfDay();
        $previousMonth = $today->subMonthNoOverflow();
        $salaryEffectiveDate = $previousMonth->startOfMonth()->toDateString();

        foreach ($allSeededEmployees as $offset => $emp) {
            if (! $emp->activeSalary()->exists()) {
                $salaryAssignmentService->assignSalary($emp->id, [
                    'salary_structure_id' => $standardStructure->id,
                    'effective_date' => $salaryEffectiveDate,
                    'base_salary' => (string) $emp->basic_salary,
                    'payment_method' => PaymentMethod::BANK_TRANSFER->value,
                ]);
            }

            LeaveBalance::query()->updateOrCreate(
                [
                    'employee_id' => $emp->id,
                    'leave_type_id' => $annualLeave->id,
                    'year' => 2026,
                ],
                [
                    'total_quota' => 12,
                    'used_quota' => $offset % 3,
                    'pending_quota' => 0,
                    'created_by' => $hrAdmin->id,
                    'updated_by' => $hrAdmin->id,
                ],
            );

            LeaveBalance::query()->updateOrCreate(
                [
                    'employee_id' => $emp->id,
                    'leave_type_id' => $sickLeave->id,
                    'year' => 2026,
                ],
                [
                    'total_quota' => 14,
                    'used_quota' => 0,
                    'pending_quota' => 0,
                    'created_by' => $hrAdmin->id,
                    'updated_by' => $hrAdmin->id,
                ],
            );

            // Generate 30 calendar days of realistic weekday attendance records
            for ($daysAgo = 30; $daysAgo >= 1; $daysAgo--) {
                $date = $today->subDays($daysAgo);
                if ($date->isWeekend()) {
                    continue;
                }

                $isLateDay = (($daysAgo + $offset) % 9) === 0;
                $clockIn = $isLateDay
                    ? $date->setTime(9, 28, 0)
                    : $date->setTime(8, 52, 0);
                $clockOut = $date->setTime(17, 15, 0);
                $lateMinutes = $isLateDay ? 28 : 0;
                $workDurationMinutes = (int) $clockIn->diffInMinutes($clockOut);

                $attendanceAttributes = [
                    'shift_id' => $regularShift->id,
                    'clock_in' => $clockIn,
                    'clock_out' => $clockOut,
                    'clock_in_ip' => '10.10.20.'.(10 + $offset),
                    'clock_out_ip' => '10.10.20.'.(10 + $offset),
                    'clock_in_location' => '-6.208763,106.845599',
                    'clock_out_location' => '-6.208763,106.845599',
                    'late_minutes' => $lateMinutes,
                    'early_leave_minutes' => 0,
                    'work_duration_minutes' => $workDurationMinutes,
                    'status' => $isLateDay
                        ? AttendanceStatus::LATE->value
                        : AttendanceStatus::PRESENT->value,
                    'created_by' => $emp->user_id,
                    'updated_by' => $emp->user_id,
                ];

                $existingAttendance = Attendance::query()
                    ->where('employee_id', $emp->id)
                    ->whereDate('date', $date->toDateString())
                    ->first();

                if ($existingAttendance !== null) {
                    $existingAttendance->update($attendanceAttributes);
                } else {
                    Attendance::query()->create([
                        'employee_id' => $emp->id,
                        'date' => $date->toDateString(),
                        ...$attendanceAttributes,
                    ]);
                }
            }
        }

        // 8. Generate & Approve Previous Month's Payroll Cycle (Immutable APPROVED batch + PUBLISHED payslips)
        $lastMonthPeriod = $previousMonth->format('Y-m');
        $existingBatch = $payrollRepository->findByPeriod($lastMonthPeriod);

        if ($existingBatch === null) {
            $generatedBatch = $payrollService->generatePayroll(
                period: $lastMonthPeriod,
                startDate: $previousMonth->startOfMonth()->toDateString(),
                endDate: $previousMonth->endOfMonth()->toDateString(),
                adminId: $hrAdmin->id,
                title: 'Payroll '.$previousMonth->format('F Y'),
            );

            $payrollService->approvePayroll($generatedBatch->id, $superadmin->id);
        }
    }

    private function upsertUser(string $email, string $name, RoleName $role): User
    {
        /** @var User $user */
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => 'password',
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );

        if (! $user->hasRole($role->value)) {
            $user->syncRoles([$role->value]);
        }

        return $user;
    }

    private function upsertEmployee(
        User $user,
        string $code,
        int $departmentId,
        int $positionId,
        int $shiftId,
        ?int $managerId,
        string $grade,
        string $baseSalary,
        string $nik,
        string $npwp,
        string $bankAccount,
        string $bankName,
        int $actorId,
    ): Employee {
        /** @var Employee $employee */
        $employee = Employee::query()->updateOrCreate(
            ['employee_code' => $code],
            [
                'user_id' => $user->id,
                'full_name' => $user->name,
                'department_id' => $departmentId,
                'position_id' => $positionId,
                'shift_id' => $shiftId,
                'manager_id' => $managerId,
                'join_date' => '2025-01-15',
                'status' => EmploymentStatus::PERMANENT->value,
                'salary_grade' => $grade,
                'basic_salary' => $baseSalary,
                'national_id_number' => $nik,
                'tax_id_number' => $npwp,
                'bank_account_number' => $bankAccount,
                'bank_name' => $bankName,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ],
        );

        return $employee;
    }
}
