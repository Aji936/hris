<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Payroll;

use App\Models\User;
use App\Modules\Attendance\Enums\AttendanceStatus;
use App\Modules\Attendance\Enums\LeaveRequestStatus;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Attendance\Models\LeaveRequest;
use App\Modules\Attendance\Models\LeaveType;
use App\Modules\Attendance\Models\Shift;
use App\Modules\CoreHR\Enums\EmploymentStatus;
use App\Modules\CoreHR\Models\Department;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\Position;
use App\Modules\Payroll\Enums\PaymentMethod;
use App\Modules\Payroll\Enums\PayrollComponentType;
use App\Modules\Payroll\Enums\PayrollStatus;
use App\Modules\Payroll\Enums\PayslipStatus;
use App\Modules\Payroll\Jobs\ProcessMonthlyPayrollJob;
use App\Modules\Payroll\Services\PayrollService;
use App\Modules\Payroll\Services\SalaryAssignmentService;
use App\Modules\RBAC\Enums\RoleName;
use App\Shared\Exceptions\DomainException;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MonthlyPayrollWorkflowTest extends TestCase
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

    public function test_monthly_payroll_generation_computes_exact_decimal_math_and_attendance_deductions(): void
    {
        $hrAdmin = User::factory()->create();
        $hrAdmin->assignRole(RoleName::HR_ADMIN->value);

        $mealAllowance = $this->salaryAssignmentService->createComponent([
            'code' => 'MEAL_ALLOWANCE',
            'name' => 'Meal Allowance',
            'type' => PayrollComponentType::ALLOWANCE,
            'is_taxable' => true,
            'is_fixed' => true,
        ]);

        $bpjsTk = $this->salaryAssignmentService->createComponent([
            'code' => 'BPJS_TK',
            'name' => 'BPJS Ketenagakerjaan',
            'type' => PayrollComponentType::DEDUCTION,
            'is_taxable' => false,
            'is_fixed' => false,
            'formula' => 'base_salary * 0.02',
        ]);

        $lateDeduction = $this->salaryAssignmentService->createComponent([
            'code' => 'LATE_DEDUCTION',
            'name' => 'Late Arrival Penalty',
            'type' => PayrollComponentType::DEDUCTION,
            'is_taxable' => false,
            'is_fixed' => false,
            'formula' => 'late_minutes * 1500',
        ]);

        $structure = $this->salaryAssignmentService->createStructure([
            'name' => 'Standard Compensation 2026',
            'components' => [
                ['payroll_component_id' => $mealAllowance->id, 'default_amount' => '1000000.00'],
                ['payroll_component_id' => $bpjsTk->id, 'default_amount' => '0.00'],
                ['payroll_component_id' => $lateDeduction->id, 'default_amount' => '0.00'],
            ],
        ]);

        $shift = Shift::factory()->create();

        // Employee 1 (PERMANENT): Base 11,000,000.00 | Meal +1,000,000.00 | BPJS -220,000.00 | 30m Late -45,000.00 | 1 Absent -500,000.00 => Net 11,235,000.00
        $employeeOne = Employee::factory()->create([
            'status' => EmploymentStatus::PERMANENT,
            'shift_id' => $shift->id,
        ]);
        $this->salaryAssignmentService->assignSalary($employeeOne->id, [
            'salary_structure_id' => $structure->id,
            'effective_date' => '2026-11-01',
            'base_salary' => '11000000.00',
            'payment_method' => PaymentMethod::BANK_TRANSFER,
        ]);

        Attendance::factory()->create([
            'employee_id' => $employeeOne->id,
            'shift_id' => $shift->id,
            'date' => '2026-11-02',
            'status' => AttendanceStatus::PRESENT,
            'late_minutes' => 0,
        ]);
        Attendance::factory()->create([
            'employee_id' => $employeeOne->id,
            'shift_id' => $shift->id,
            'date' => '2026-11-03',
            'status' => AttendanceStatus::LATE,
            'late_minutes' => 30,
        ]);
        Attendance::factory()->create([
            'employee_id' => $employeeOne->id,
            'shift_id' => $shift->id,
            'date' => '2026-11-04',
            'status' => AttendanceStatus::ABSENT,
            'late_minutes' => 0,
        ]);

        // Employee 2 (CONTRACT): Base 8,800,000.00 | Meal +1,000,000.00 | BPJS -176,000.00 | 2d Unpaid Leave -800,000.00 => Net 8,824,000.00
        $employeeTwo = Employee::factory()->create([
            'status' => EmploymentStatus::CONTRACT,
            'shift_id' => $shift->id,
        ]);
        $this->salaryAssignmentService->assignSalary($employeeTwo->id, [
            'salary_structure_id' => $structure->id,
            'effective_date' => '2026-11-01',
            'base_salary' => '8800000.00',
            'payment_method' => PaymentMethod::BANK_TRANSFER,
        ]);

        $unpaidType = LeaveType::factory()->create([
            'code' => 'UNPAID',
            'name' => 'Unpaid Personal Leave',
            'is_paid' => false,
        ]);
        LeaveRequest::factory()->create([
            'employee_id' => $employeeTwo->id,
            'leave_type_id' => $unpaidType->id,
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-11',
            'total_days' => 2,
            'status' => LeaveRequestStatus::APPROVED,
        ]);

        // Employee 3 (TERMINATED): Must be excluded from payroll generation
        Employee::factory()->create([
            'status' => EmploymentStatus::TERMINATED,
        ]);

        $payroll = $this->payrollService->generatePayroll(
            period: '2026-11',
            startDate: '2026-11-01',
            endDate: '2026-11-30',
            adminId: $hrAdmin->id,
            title: 'Payroll November 2026',
        );

        $this->assertSame(PayrollStatus::GENERATED, $payroll->status);
        $this->assertCount(2, $payroll->payslips);
        $this->assertSame('21800000.00', $payroll->total_gross_amount);
        $this->assertSame('1741000.00', $payroll->total_deduction_amount);
        $this->assertSame('20059000.00', $payroll->total_net_amount);

        $payslipOne = $payroll->payslips->firstWhere('employee_id', $employeeOne->id);
        $this->assertNotNull($payslipOne);
        $this->assertSame('11000000.00', $payslipOne->basic_salary);
        $this->assertSame('1000000.00', $payslipOne->total_allowance);
        $this->assertSame('765000.00', $payslipOne->total_deduction);
        $this->assertSame('11235000.00', $payslipOne->net_salary);
        $this->assertSame(2, $payslipOne->attendance_summary['present_days']);
        $this->assertSame(30, $payslipOne->attendance_summary['late_minutes']);
        $this->assertSame(1, $payslipOne->attendance_summary['absent_days']);

        $payslipTwo = $payroll->payslips->firstWhere('employee_id', $employeeTwo->id);
        $this->assertNotNull($payslipTwo);
        $this->assertSame('8800000.00', $payslipTwo->basic_salary);
        $this->assertSame('976000.00', $payslipTwo->total_deduction);
        $this->assertSame('8824000.00', $payslipTwo->net_salary);
        $this->assertSame(2, $payslipTwo->attendance_summary['unpaid_leave_days']);
    }

    public function test_employee_snapshot_preserves_historical_data_after_promotion_or_resignation(): void
    {
        $hrAdmin = User::factory()->create();
        $hrAdmin->assignRole(RoleName::HR_ADMIN->value);

        $initialDept = Department::factory()->create(['code' => 'DEPT-ENG', 'name' => 'Product Engineering']);
        $initialPosition = Position::factory()->create([
            'department_id' => $initialDept->id,
            'code' => 'POS-SWE-JR',
            'title' => 'Junior Software Engineer',
        ]);

        $employee = Employee::factory()->create([
            'employee_code' => 'EMP-2026-0099',
            'full_name' => 'Rina Kartika',
            'department_id' => $initialDept->id,
            'position_id' => $initialPosition->id,
            'national_id_number' => '3174051122330001',
            'bank_name' => 'Bank Mandiri',
            'bank_account_number' => '1240009876543',
            'basic_salary' => '10000000.00',
            'status' => EmploymentStatus::PERMANENT,
        ]);

        $payroll = $this->payrollService->generatePayroll(
            period: '2026-10',
            startDate: '2026-10-01',
            endDate: '2026-10-31',
            adminId: $hrAdmin->id,
        );

        $payslip = $payroll->payslips->firstOrFail();

        // Simulate future promotion, department transfer, bank account change, and eventual resignation
        $newDept = Department::factory()->create(['code' => 'DEPT-ARCH', 'name' => 'Enterprise Architecture']);
        $newPosition = Position::factory()->create([
            'department_id' => $newDept->id,
            'code' => 'POS-ARCH-LD',
            'title' => 'Lead Cloud Architect',
        ]);

        $employee->update([
            'department_id' => $newDept->id,
            'position_id' => $newPosition->id,
            'bank_name' => 'BCA',
            'bank_account_number' => '8889990001',
            'status' => EmploymentStatus::TERMINATED,
        ]);

        $payslip->refresh();
        $snapshot = $payslip->employee_snapshot;

        $this->assertSame('Rina Kartika', $snapshot['full_name']);
        $this->assertSame('3174051122330001', $snapshot['national_id_number']);
        $this->assertSame('Junior Software Engineer', $snapshot['position_title']);
        $this->assertSame('Product Engineering', $snapshot['department_name']);
        $this->assertSame('Bank Mandiri', $snapshot['bank_name']);
        $this->assertSame('1240009876543', $snapshot['bank_account_number']);
    }

    public function test_immutability_rule_throws_domain_exception_when_modifying_or_deleting_approved_payslip(): void
    {
        $hrAdmin = User::factory()->create();
        $hrAdmin->assignRole(RoleName::HR_ADMIN->value);

        $superadmin = User::factory()->create();
        $superadmin->assignRole(RoleName::SUPERADMIN->value);

        $allowance = $this->salaryAssignmentService->createComponent([
            'code' => 'TRANSPORT',
            'name' => 'Transport Allowance',
            'type' => PayrollComponentType::ALLOWANCE,
        ]);
        $structure = $this->salaryAssignmentService->createStructure([
            'name' => 'Immutable Test Structure',
            'components' => [
                ['payroll_component_id' => $allowance->id, 'default_amount' => '500000.00'],
            ],
        ]);

        $employee = Employee::factory()->create(['status' => EmploymentStatus::PERMANENT]);
        $this->salaryAssignmentService->assignSalary($employee->id, [
            'salary_structure_id' => $structure->id,
            'effective_date' => '2026-11-01',
            'base_salary' => '15000000.00',
        ]);

        $payroll = $this->payrollService->generatePayroll(
            period: '2026-11',
            startDate: '2026-11-01',
            endDate: '2026-11-30',
            adminId: $hrAdmin->id,
        );

        $payslip = $payroll->payslips->firstOrFail();
        $payslipItem = $payslip->items->firstOrFail();

        // Approve payroll -> locks Payroll as APPROVED and publishes all Payslips
        $approvedPayroll = $this->payrollService->approvePayroll($payroll->id, $superadmin->id);
        $this->assertSame(PayrollStatus::APPROVED, $approvedPayroll->status);
        $this->assertSame(PayslipStatus::PUBLISHED, $payslip->fresh()->status);

        // 1. Attempting to edit Payslip throws DomainException
        try {
            $payslip->update(['basic_salary' => '99000000.00']);
            $this->fail('Expected DomainException when updating Payslip of APPROVED payroll.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }

        // 2. Attempting to delete Payslip throws DomainException
        try {
            $payslip->delete();
            $this->fail('Expected DomainException when deleting Payslip of APPROVED payroll.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }

        // 3. Attempting to edit PayslipItem throws DomainException
        try {
            $payslipItem->update(['amount' => '9999999.00']);
            $this->fail('Expected DomainException when updating PayslipItem of APPROVED payroll.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }

        // 4. Attempting to delete PayslipItem throws DomainException
        try {
            $payslipItem->delete();
            $this->fail('Expected DomainException when deleting PayslipItem of APPROVED payroll.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }

        // 5. Attempting to regenerate an already APPROVED period throws DomainException
        $this->expectException(DomainException::class);
        $this->payrollService->generatePayroll(
            period: '2026-11',
            startDate: '2026-11-01',
            endDate: '2026-11-30',
            adminId: $hrAdmin->id,
        );
    }

    public function test_ess_payslip_isolation_background_job_and_superadmin_approval_over_http(): void
    {
        $hrAdmin = User::factory()->create();
        $hrAdmin->assignRole(RoleName::HR_ADMIN->value);

        $superadmin = User::factory()->create();
        $superadmin->assignRole(RoleName::SUPERADMIN->value);

        $userA = User::factory()->create();
        $userA->assignRole(RoleName::EMPLOYEE->value);
        $employeeA = Employee::factory()->create([
            'user_id' => $userA->id,
            'status' => EmploymentStatus::PERMANENT,
            'basic_salary' => '12500000.00',
        ]);

        $userB = User::factory()->create();
        $userB->assignRole(RoleName::EMPLOYEE->value);
        $employeeB = Employee::factory()->create([
            'user_id' => $userB->id,
            'status' => EmploymentStatus::PERMANENT,
            'basic_salary' => '14000000.00',
        ]);

        // 1. Verify async Redis queue dispatch via HTTP (202 Accepted)
        Queue::fake();

        $asyncResponse = $this->actingAs($hrAdmin)
            ->postJson(route('payroll.batches.generate'), [
                'payroll_period' => '2026-11',
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-30',
                'title' => 'Payroll November 2026',
                'async' => true,
            ])
            ->assertStatus(202)
            ->assertJsonPath('data.status', PayrollStatus::PROCESSING->value);

        $payrollId = (int) $asyncResponse->json('data.id');

        Queue::assertPushedOn('payroll', ProcessMonthlyPayrollJob::class, function (ProcessMonthlyPayrollJob $job) use ($hrAdmin): bool {
            $job->handle($this->payrollService);

            return $job->period === '2026-11' && $job->adminId === $hrAdmin->id;
        });

        // Fetch generated payslips
        $batchShow = $this->actingAs($hrAdmin)
            ->getJson(route('payroll.batches.show', $payrollId))
            ->assertOk()
            ->assertJsonPath('data.status', PayrollStatus::GENERATED->value);

        $payslipsData = collect($batchShow->json('data.payslips'));
        $payslipAId = (int) $payslipsData->firstWhere('employee_id', $employeeA->id)['id'];
        $payslipBId = (int) $payslipsData->firstWhere('employee_id', $employeeB->id)['id'];

        // 2. Regular employee is blocked from viewing company-wide payroll batches (403)
        $this->actingAs($userA)
            ->getJson(route('payroll.batches.index'))
            ->assertForbidden();

        // 3. Before approval (status = DRAFT), Employee A cannot even access their own unpublished payslip (403)
        $this->actingAs($userA)
            ->getJson(route('payroll.payslips.show', $payslipAId))
            ->assertForbidden();

        $this->actingAs($userA)
            ->getJson(route('payroll.payslips.mine'))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // 4. HR Admin cannot approve payroll batch (403 Forbidden — reserved for Superadmin / Finance Director)
        $this->actingAs($hrAdmin)
            ->postJson(route('payroll.batches.approve', $payrollId))
            ->assertForbidden();

        // 5. Superadmin approves payroll batch -> status APPROVED & all payslips PUBLISHED
        $this->actingAs($superadmin)
            ->postJson(route('payroll.batches.approve', $payrollId))
            ->assertOk()
            ->assertJsonPath('data.status', PayrollStatus::APPROVED->value)
            ->assertJsonPath('data.approved_by', $superadmin->id);

        // 6. Employee A can now view and download their own PUBLISHED payslip (200 OK)
        $this->actingAs($userA)
            ->getJson(route('payroll.payslips.mine'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $payslipAId);

        $this->actingAs($userA)
            ->getJson(route('payroll.payslips.show', $payslipAId))
            ->assertOk()
            ->assertJsonPath('data.status', PayslipStatus::PUBLISHED->value)
            ->assertJsonPath('data.net_salary', '12500000.00');

        $this->actingAs($userA)
            ->getJson(route('payroll.payslips.download', $payslipAId))
            ->assertOk()
            ->assertJsonPath('meta.document_type', 'PAYSLIP_STATEMENT');

        // 7. Employee A attempting to view or download Employee B's payslip receives 403 Forbidden
        $this->actingAs($userA)
            ->getJson(route('payroll.payslips.show', $payslipBId))
            ->assertForbidden();

        $this->actingAs($userA)
            ->getJson(route('payroll.payslips.download', $payslipBId))
            ->assertForbidden();
    }
}
