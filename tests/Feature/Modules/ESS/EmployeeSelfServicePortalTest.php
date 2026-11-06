<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\ESS;

use App\Models\User;
use App\Modules\Attendance\Models\LeaveBalance;
use App\Modules\Attendance\Models\LeaveType;
use App\Modules\Attendance\Models\Shift;
use App\Modules\CoreHR\Enums\EmploymentStatus;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\Payroll\Enums\PaymentMethod;
use App\Modules\Payroll\Enums\PayrollComponentType;
use App\Modules\Payroll\Services\PayrollService;
use App\Modules\Payroll\Services\SalaryAssignmentService;
use App\Modules\RBAC\Enums\RoleName;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeSelfServicePortalTest extends TestCase
{
    use RefreshDatabase;

    private PayrollService $payrollService;

    private SalaryAssignmentService $salaryAssignmentService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->payrollService = $this->app->make(PayrollService::class);
        $this->salaryAssignmentService = $this->app->make(SalaryAssignmentService::class);
    }

    public function test_employee_can_view_attendance_portal_and_perform_inertia_clock_in_and_clock_out(): void
    {
        $shift = Shift::factory()->create([
            'name' => 'Regular Office Shift',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'late_grace_period_minutes' => 15,
        ]);

        $user = User::factory()->create();
        $user->assignRole(RoleName::EMPLOYEE->value);

        $employee = Employee::factory()->create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'employee_code' => 'EMP-ESS-001',
            'full_name' => 'Siti Aminah',
            'status' => EmploymentStatus::PERMANENT,
        ]);

        $this->assertFileExists(resource_path('js/Modules/Attendance/Pages/Index.tsx'));

        $this->actingAs($user)
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Attendance/Index', false)
                ->where('employee.id', $employee->id)
                ->where('employee.employee_code', 'EMP-ESS-001')
                ->where('todayAttendance', null)
                ->has('monthlyRecords', 0)
            );

        $this->actingAs($user)
            ->from(route('attendance.index'))
            ->post(route('attendance.clock-in'), [
                'location' => '-6.208800,106.845600',
            ])
            ->assertRedirect(route('attendance.index'))
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Attendance/Index', false)
                ->where('todayAttendance.employee_id', $employee->id)
                ->where('todayAttendance.clock_in_location', '-6.208800,106.845600')
                ->has('monthlyRecords', 1)
            );

        $this->actingAs($user)
            ->from(route('attendance.index'))
            ->post(route('attendance.clock-out'), [
                'location' => '-6.208800,106.845600',
            ])
            ->assertRedirect(route('attendance.index'))
            ->assertSessionHas('success');
    }

    public function test_employee_can_view_leave_portal_and_submit_leave_with_attachment(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $user->assignRole(RoleName::EMPLOYEE->value);

        $employee = Employee::factory()->create([
            'user_id' => $user->id,
            'employee_code' => 'EMP-ESS-002',
            'status' => EmploymentStatus::PERMANENT,
        ]);

        $currentYear = (int) now()->year;

        $sickLeave = LeaveType::factory()->create([
            'code' => 'SICK',
            'name' => 'Sick Leave',
            'default_quota_days' => 14,
            'is_paid' => true,
            'requires_attachment' => true,
        ]);

        $balance = LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $sickLeave->id,
            'year' => $currentYear,
            'total_quota' => 14,
            'used_quota' => 1,
            'pending_quota' => 0,
        ]);

        $this->assertFileExists(resource_path('js/Modules/Attendance/Pages/Leave/Index.tsx'));

        $this->actingAs($user)
            ->get(route('attendance.leave-requests.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Attendance/Leave/Index', false)
                ->where('hasEmployeeProfile', true)
                ->where('currentYear', $currentYear)
                ->has('leaveBalances', 1)
                ->where('leaveBalances.0.remaining_quota', 13)
                ->has('leaveTypes', 1)
            );

        $startDate = sprintf('%d-11-18', $currentYear);
        $endDate = sprintf('%d-11-19', $currentYear);
        $attachment = UploadedFile::fake()->create('medical-certificate.pdf', 120, 'application/pdf');

        $this->actingAs($user)
            ->from(route('attendance.leave-requests.index'))
            ->post(route('attendance.leave-requests.store'), [
                'leave_type_id' => $sickLeave->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'reason' => 'Doctor advised two days of rest',
                'attachment' => $attachment,
            ])
            ->assertRedirect(route('attendance.leave-requests.index'))
            ->assertSessionHas('success');

        $balance->refresh();
        $this->assertSame(2, $balance->pending_quota);
        $this->assertSame(11, $balance->remainingQuota());

        $this->actingAs($user)
            ->get(route('attendance.leave-requests.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Attendance/Leave/Index', false)
                ->has('leaveRequests', 1)
                ->where('leaveRequests.0.status', 'PENDING')
                ->where('leaveRequests.0.total_days', 2)
            );
    }

    public function test_employee_my_payslips_page_lists_only_own_published_payslips(): void
    {
        $hrAdmin = User::factory()->create();
        $hrAdmin->assignRole(RoleName::HR_ADMIN->value);

        $superadmin = User::factory()->create();
        $superadmin->assignRole(RoleName::SUPERADMIN->value);

        $employeeUser = User::factory()->create();
        $employeeUser->assignRole(RoleName::EMPLOYEE->value);
        $employee = Employee::factory()->create([
            'user_id' => $employeeUser->id,
            'employee_code' => 'EMP-ESS-003',
            'full_name' => 'Rina Kartika',
            'status' => EmploymentStatus::PERMANENT,
        ]);

        $otherUser = User::factory()->create();
        $otherUser->assignRole(RoleName::EMPLOYEE->value);
        $otherEmployee = Employee::factory()->create([
            'user_id' => $otherUser->id,
            'employee_code' => 'EMP-ESS-004',
            'full_name' => 'Andi Wijaya',
            'status' => EmploymentStatus::PERMANENT,
        ]);

        $allowance = $this->salaryAssignmentService->createComponent([
            'code' => 'TRANSPORT_ESS',
            'name' => 'Transport Allowance',
            'type' => PayrollComponentType::ALLOWANCE,
        ]);
        $structure = $this->salaryAssignmentService->createStructure([
            'name' => 'ESS Package',
            'components' => [
                ['payroll_component_id' => $allowance->id, 'default_amount' => '1000000.00'],
            ],
        ]);

        $this->salaryAssignmentService->assignSalary($employee->id, [
            'salary_structure_id' => $structure->id,
            'effective_date' => '2026-10-01',
            'base_salary' => '12000000.00',
            'payment_method' => PaymentMethod::BANK_TRANSFER,
        ]);
        $this->salaryAssignmentService->assignSalary($otherEmployee->id, [
            'salary_structure_id' => $structure->id,
            'effective_date' => '2026-10-01',
            'base_salary' => '18000000.00',
            'payment_method' => PaymentMethod::BANK_TRANSFER,
        ]);

        // Approved/Published October batch
        $octoberPayroll = $this->payrollService->generatePayroll(
            period: '2026-10',
            startDate: '2026-10-01',
            endDate: '2026-10-31',
            adminId: $hrAdmin->id,
        );
        $this->payrollService->approvePayroll($octoberPayroll->id, $superadmin->id);

        // Unapproved/Draft November batch (must NOT appear in ESS)
        $this->payrollService->generatePayroll(
            period: '2026-11',
            startDate: '2026-11-01',
            endDate: '2026-11-30',
            adminId: $hrAdmin->id,
        );

        $this->assertFileExists(resource_path('js/Modules/Payroll/Pages/Payslips/MyPayslips.tsx'));

        $this->actingAs($employeeUser)
            ->get(route('payroll.payslips.mine'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Payroll/Payslips/MyPayslips', false)
                ->where('hasEmployeeProfile', true)
                ->where('summary.published_count', 1)
                ->where('summary.latest_net_salary', '13000000.00')
                ->has('payslips', 1)
                ->where('payslips.0.employee_id', $employee->id)
                ->where('payslips.0.status', 'PUBLISHED')
            );
    }
}

