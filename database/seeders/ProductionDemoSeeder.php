<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Attendance\Enums\AttendanceStatus;
use App\Modules\Attendance\Enums\LeaveRequestStatus;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Attendance\Models\LeaveBalance;
use App\Modules\Attendance\Models\LeaveRequest;
use App\Modules\Attendance\Models\LeaveType;
use App\Modules\Attendance\Models\Shift;
use App\Modules\CoreHR\Enums\AnnouncementCategory;
use App\Modules\CoreHR\Enums\EmploymentStatus;
use App\Modules\CoreHR\Enums\MaritalStatus;
use App\Modules\CoreHR\Models\Announcement;
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
        $today = CarbonImmutable::now()->startOfDay();

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
            phoneNumber: '08119001001',
            address: 'Jl. Jenderal Sudirman Kav. 52-53, Kebayoran Baru, Jakarta Selatan',
            maritalStatus: MaritalStatus::MARRIED,
            dependentsCount: 2,
            dateOfBirth: '1986-04-12',
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
            phoneNumber: '08119001002',
            address: 'Jl. HR Rasuna Said Kav. C-22, Kuningan, Jakarta Selatan',
            maritalStatus: MaritalStatus::MARRIED,
            dependentsCount: 1,
            dateOfBirth: '1988-09-20',
        );

        // 6. Seed 10 Active Employees with Encrypted PII, Personal Profile & Realistic Birth Dates (including 2 approaching retirement at age 54-55)
        $employeesSpec = [
            ['idx' => 1, 'name' => 'Budi Santoso', 'dept' => $engDept->id, 'pos' => 'POS-ENG-SWE', 'mgr' => $engManagerEmployee->id, 'grade' => 'S3', 'salary' => '20000000.00', 'bank' => 'BCA', 'marital' => MaritalStatus::MARRIED, 'dependents' => 2, 'address' => 'Jl. Kemang Raya No. 18, Mampang Prapatan, Jakarta Selatan', 'dob' => $today->subYears(55)->addDays(45)->toDateString()],
            ['idx' => 2, 'name' => 'Siti Rahmawati', 'dept' => $engDept->id, 'pos' => 'POS-ENG-SWE', 'mgr' => $engManagerEmployee->id, 'grade' => 'S3', 'salary' => '19500000.00', 'bank' => 'Bank Mandiri', 'marital' => MaritalStatus::MARRIED, 'dependents' => 1, 'address' => 'Jl. Tebet Barat Dalam Raya No. 45, Jakarta Selatan', 'dob' => '1992-03-14'],
            ['idx' => 3, 'name' => 'Dimas Saputra', 'dept' => $engDept->id, 'pos' => 'POS-ENG-QA', 'mgr' => $engManagerEmployee->id, 'grade' => 'S2', 'salary' => '15000000.00', 'bank' => 'BNI', 'marital' => MaritalStatus::SINGLE, 'dependents' => 0, 'address' => 'Jl. Margonda Raya No. 112, Depok, Jawa Barat', 'dob' => '1996-07-22'],
            ['idx' => 4, 'name' => 'Rina Kartika', 'dept' => $engDept->id, 'pos' => 'POS-ENG-QA', 'mgr' => $engManagerEmployee->id, 'grade' => 'S2', 'salary' => '14500000.00', 'bank' => 'BCA', 'marital' => MaritalStatus::SINGLE, 'dependents' => 0, 'address' => 'Jl. Cempaka Putih Tengah No. 27, Jakarta Pusat', 'dob' => '1997-11-05'],
            ['idx' => 5, 'name' => 'Fajar Nugroho', 'dept' => $hrDept->id, 'pos' => 'POS-HR-SPEC', 'mgr' => $opsManagerEmployee->id, 'grade' => 'S2', 'salary' => '13500000.00', 'bank' => 'BRI', 'marital' => MaritalStatus::MARRIED, 'dependents' => 3, 'address' => 'Jl. Bintaro Utama 3A Blok D12, Tangerang Selatan', 'dob' => '1990-01-19'],
            ['idx' => 6, 'name' => 'Lestari Wulandari', 'dept' => $hrDept->id, 'pos' => 'POS-HR-TA', 'mgr' => $opsManagerEmployee->id, 'grade' => 'S2', 'salary' => '12500000.00', 'bank' => 'BCA', 'marital' => MaritalStatus::DIVORCED, 'dependents' => 1, 'address' => 'Jl. Kelapa Gading Boulevard Blok QA3, Jakarta Utara', 'dob' => '1993-06-30'],
            ['idx' => 7, 'name' => 'Hendra Wijaya', 'dept' => $finDept->id, 'pos' => 'POS-FIN-ACC', 'mgr' => $opsManagerEmployee->id, 'grade' => 'S3', 'salary' => '16500000.00', 'bank' => 'Bank Mandiri', 'marital' => MaritalStatus::MARRIED, 'dependents' => 2, 'address' => 'Jl. Panjang Kebon Jeruk No. 88, Jakarta Barat', 'dob' => $today->subYears(55)->addDays(120)->toDateString()],
            ['idx' => 8, 'name' => 'Putri Maharani', 'dept' => $finDept->id, 'pos' => 'POS-FIN-ACC', 'mgr' => $opsManagerEmployee->id, 'grade' => 'S3', 'salary' => '16000000.00', 'bank' => 'BNI', 'marital' => MaritalStatus::SINGLE, 'dependents' => 0, 'address' => 'Jl. Cikini Raya No. 60, Menteng, Jakarta Pusat', 'dob' => '1995-08-17'],
            ['idx' => 9, 'name' => 'Rizky Ramadhan', 'dept' => $opsDept->id, 'pos' => 'POS-OPS-COOR', 'mgr' => $opsManagerEmployee->id, 'grade' => 'S1', 'salary' => '11000000.00', 'bank' => 'BCA', 'marital' => MaritalStatus::SINGLE, 'dependents' => 0, 'address' => 'Jl. Kalimalang Raya No. 19, Bekasi Barat', 'dob' => '1998-02-25'],
            ['idx' => 10, 'name' => 'Anisa Permata', 'dept' => $opsDept->id, 'pos' => 'POS-OPS-COOR', 'mgr' => $opsManagerEmployee->id, 'grade' => 'S1', 'salary' => '10500000.00', 'bank' => 'Bank Mandiri', 'marital' => MaritalStatus::WIDOWED, 'dependents' => 1, 'address' => 'Jl. Cinere Raya No. 34, Depok, Jawa Barat', 'dob' => '1991-12-09'],
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
                phoneNumber: sprintf('08128800%04d', $index),
                address: $row['address'],
                maritalStatus: $row['marital'],
                dependentsCount: $row['dependents'],
                dateOfBirth: $row['dob'],
            );

            $allSeededEmployees[] = $empRecord;
        }

        // 7. Assign Active Salaries, 2026 Leave Balances & 30-Day Attendance History
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

        // 8. Seed Sample Leave Requests (1 APPROVED & 1 PENDING)
        $firstEmployee = $allSeededEmployees[2] ?? $allSeededEmployees[0];
        LeaveRequest::query()->updateOrCreate(
            [
                'employee_id' => $firstEmployee->id,
                'start_date' => $previousMonth->setDay(14)->toDateString(),
                'end_date' => $previousMonth->setDay(15)->toDateString(),
            ],
            [
                'leave_type_id' => $annualLeave->id,
                'total_days' => 2,
                'reason' => 'Annual family gathering out of town',
                'status' => LeaveRequestStatus::APPROVED->value,
                'approver_id' => $engManagerUser->id,
                'approved_at' => $previousMonth->setDay(12)->setTime(10, 30),
                'created_by' => $firstEmployee->user_id,
                'updated_by' => $engManagerUser->id,
            ],
        );

        $secondEmployee = $allSeededEmployees[3] ?? $allSeededEmployees[1];
        LeaveRequest::query()->updateOrCreate(
            [
                'employee_id' => $secondEmployee->id,
                'start_date' => $today->addDays(5)->toDateString(),
                'end_date' => $today->addDays(6)->toDateString(),
            ],
            [
                'leave_type_id' => $annualLeave->id,
                'total_days' => 2,
                'reason' => 'Personal administrative matters',
                'status' => LeaveRequestStatus::PENDING->value,
                'created_by' => $secondEmployee->user_id,
                'updated_by' => $secondEmployee->user_id,
            ],
        );

        // 9. Generate & Approve Previous Month's Payroll Cycle (Immutable APPROVED batch + PUBLISHED payslips)
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

        // 10. Seed Realistic Company Announcements for Dashboard Widget
        Announcement::query()->updateOrCreate(
            ['title' => 'Kebijakan Baru Pengajuan Cuti & Izin (3-Level Approval & Cetak PDF)'],
            [
                'category' => AnnouncementCategory::POLICY->value,
                'content' => "Seluruh karyawan kini dapat mengajukan permohonan Cuti Tahunan, Sakit, Izin Khusus, Datang Terlambat, Pulang Awal, maupun Lupa Absen secara digital melalui menu Leave & Permit Application.\n\nSetiap permohonan akan melalui verifikasi bertingkat (Approved I Asst. Head → Approved II Dept. Manager → Approved III HRD) dan formulir resmi dapat diunduh dalam format PDF.",
                'is_pinned' => true,
                'is_active' => true,
                'published_at' => $today->subDays(2)->setTime(9, 0),
                'expires_at' => null,
                'created_by' => $hrAdmin->id,
                'updated_by' => $hrAdmin->id,
            ],
        );

        Announcement::query()->updateOrCreate(
            ['title' => 'Jadwal Libur Nasional & Cuti Bersama Akhir Tahun 2026'],
            [
                'category' => AnnouncementCategory::INFO->value,
                'content' => "Mengacu pada Surat Keputusan Bersama (SKB) 3 Menteri, diberitahukan kepada seluruh karyawan bahwa libur Hari Raya dan Cuti Bersama akhir tahun akan berlangsung pada tanggal 24–25 Desember 2026.\n\nBagi karyawan di divisi operasional yang bertugas sesuai jadwal shift, perhitungan uang lembur dan penggantian hari libur berlaku sesuai Peraturan Perusahaan.",
                'is_pinned' => true,
                'is_active' => true,
                'published_at' => $today->subDays(1)->setTime(10, 30),
                'expires_at' => null,
                'created_by' => $hrAdmin->id,
                'updated_by' => $hrAdmin->id,
            ],
        );

        Announcement::query()->updateOrCreate(
            ['title' => 'Undangan Townhall Meeting & Apresiasi Karyawan Kuartal IV'],
            [
                'category' => AnnouncementCategory::EVENT->value,
                'content' => 'Manajemen mengundang seluruh karyawan dari departemen Engineering, Operations, HR, dan Finance untuk hadir dalam acara Townhall Meeting Kuartal IV yang akan diselenggarakan pada hari Jumat pukul 14.00 WIB di Auditorium Utama & melalui Zoom.',
                'is_pinned' => false,
                'is_active' => true,
                'published_at' => $today->setTime(8, 30),
                'expires_at' => $today->addDays(30)->endOfDay(),
                'created_by' => $superadmin->id,
                'updated_by' => $superadmin->id,
            ],
        );
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
        string $phoneNumber = '081200000000',
        string $address = 'Jakarta, Indonesia',
        MaritalStatus $maritalStatus = MaritalStatus::SINGLE,
        int $dependentsCount = 0,
        string $dateOfBirth = '1992-05-15',
    ): Employee {
        $retirementDate = CarbonImmutable::parse($dateOfBirth)->addYears(Employee::RETIREMENT_AGE_YEARS)->toDateString();

        /** @var Employee $employee */
        $employee = Employee::query()->updateOrCreate(
            ['employee_code' => $code],
            [
                'user_id' => $user->id,
                'full_name' => $user->name,
                'date_of_birth' => $dateOfBirth,
                'retirement_date' => $retirementDate,
                'phone_number' => $phoneNumber,
                'address' => $address,
                'marital_status' => $maritalStatus->value,
                'dependents_count' => $dependentsCount,
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
