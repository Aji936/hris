<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Attendance\Models\LeaveBalance;
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
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\RBAC\Enums\RoleName;
use App\Modules\RBAC\Models\AuditLog;
use App\Shared\Exceptions\DomainException;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EndToEndHrisWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_end_to_end_hris_lifecycle_and_security_isolation(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        // 1. Setup System Actors & Organization Hierarchy
        $superadmin = User::factory()->create(['email' => 'superadmin.e2e@hris.local']);
        $superadmin->assignRole(RoleName::SUPERADMIN->value);

        $hrAdmin = User::factory()->create(['email' => 'hr.e2e@hris.local']);
        $hrAdmin->assignRole(RoleName::HR_ADMIN->value);

        $engineeringDept = Department::factory()->create([
            'code' => 'ENG-E2E',
            'name' => 'Engineering Division',
        ]);

        $managerPosition = Position::factory()->create([
            'department_id' => $engineeringDept->id,
            'code' => 'POS-E2E-MGR',
            'title' => 'Engineering Manager',
            'level' => 'M1',
        ]);

        $engineerPosition = Position::factory()->create([
            'department_id' => $engineeringDept->id,
            'code' => 'POS-E2E-SWE',
            'title' => 'Senior Backend Engineer',
            'level' => 'S3',
        ]);

        $regularShift = Shift::factory()->create([
            'name' => 'Head Office Shift',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'late_grace_period_minutes' => 15,
        ]);

        $managerUser = User::factory()->create(['email' => 'manager.e2e@hris.local']);
        $managerUser->assignRole(RoleName::MANAGER->value);
        $managerEmployee = Employee::factory()->create([
            'user_id' => $managerUser->id,
            'employee_code' => 'EMP-E2E-MGR',
            'full_name' => 'Bagus Santoso',
            'department_id' => $engineeringDept->id,
            'position_id' => $managerPosition->id,
            'shift_id' => $regularShift->id,
            'status' => EmploymentStatus::PERMANENT,
            'basic_salary' => '26000000.00',
        ]);

        // Colleague account for IDOR & cross-employee isolation checks
        $colleagueUser = User::factory()->create(['email' => 'colleague.e2e@hris.local']);
        $colleagueUser->assignRole(RoleName::EMPLOYEE->value);
        Employee::factory()->create([
            'user_id' => $colleagueUser->id,
            'employee_code' => 'EMP-E2E-COL',
            'full_name' => 'Doni Saputra',
            'department_id' => $engineeringDept->id,
            'position_id' => $engineerPosition->id,
            'shift_id' => $regularShift->id,
            'status' => EmploymentStatus::PERMANENT,
            'basic_salary' => '18000000.00',
        ]);

        // 2. Step A: HR Admin Onboards a New Employee via HTTP Endpoint
        $onboardResponse = $this->actingAs($hrAdmin)->postJson(route('core-hr.employees.store'), [
            'full_name' => 'Dewi Lestari',
            'email' => 'dewi.lestari@hris.local',
            'password' => 'SecurePass#2026',
            'employee_code' => 'EMP-E2E-001',
            'department_id' => $engineeringDept->id,
            'position_id' => $engineerPosition->id,
            'shift_id' => $regularShift->id,
            'manager_id' => $managerEmployee->id,
            'join_date' => '2026-10-01',
            'status' => EmploymentStatus::PERMANENT->value,
            'salary_grade' => 'S3',
            'basic_salary' => '22000000.00',
            'national_id_number' => '3174055204960008',
            'tax_id_number' => '092345678901000',
            'bank_account_number' => '7710099887766',
            'bank_name' => 'BCA',
        ]);

        $onboardResponse->assertCreated()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');

        $onboardedEmployeeId = (int) $onboardResponse->json('data.id');
        /** @var Employee $dewiEmployee */
        $dewiEmployee = Employee::query()->with('user')->findOrFail($onboardedEmployeeId);
        /** @var User $dewiUser */
        $dewiUser = $dewiEmployee->user;

        $this->assertTrue($dewiUser->hasRole(RoleName::EMPLOYEE->value));
        $this->assertSame('3174055204960008', $dewiEmployee->national_id_number);

        $rawEmployeeRow = DB::table('employees')->where('id', $dewiEmployee->id)->first();
        $this->assertNotNull($rawEmployeeRow);
        $this->assertNotSame('3174055204960008', $rawEmployeeRow->national_id_number);
        $this->assertNotSame('7710099887766', $rawEmployeeRow->bank_account_number);

        // IDOR Guard: Colleague cannot view Dewi's employee profile
        $this->actingAs($colleagueUser)
            ->getJson(route('core-hr.employees.show', $dewiEmployee->id))
            ->assertForbidden();

        // 3. Step B: HR Admin Configures Payroll Components, Structure & Assigns Salary
        $mealCompRes = $this->actingAs($hrAdmin)->postJson(route('payroll.components.store'), [
            'code' => 'MEAL_ALLOWANCE_E2E',
            'name' => 'Meal Allowance',
            'type' => PayrollComponentType::ALLOWANCE->value,
            'is_taxable' => true,
            'is_fixed' => true,
            'is_active' => true,
        ])->assertCreated();

        $bpjsCompRes = $this->actingAs($hrAdmin)->postJson(route('payroll.components.store'), [
            'code' => 'BPJS_TK_E2E',
            'name' => 'BPJS Ketenagakerjaan',
            'type' => PayrollComponentType::DEDUCTION->value,
            'is_taxable' => false,
            'is_fixed' => true,
            'is_active' => true,
        ])->assertCreated();

        $lateCompRes = $this->actingAs($hrAdmin)->postJson(route('payroll.components.store'), [
            'code' => 'LATE_DEDUCTION',
            'name' => 'Late Attendance Deduction',
            'type' => PayrollComponentType::DEDUCTION->value,
            'is_taxable' => false,
            'is_fixed' => false,
            'is_active' => true,
        ])->assertCreated();

        $structureRes = $this->actingAs($hrAdmin)->postJson(route('payroll.structures.store'), [
            'name' => 'Engineering S3 Compensation',
            'description' => 'E2E engineering package',
            'components' => [
                ['payroll_component_id' => $mealCompRes->json('data.id'), 'default_amount' => '1500000.00'],
                ['payroll_component_id' => $bpjsCompRes->json('data.id'), 'default_amount' => '300000.00'],
                ['payroll_component_id' => $lateCompRes->json('data.id'), 'default_amount' => '50000.00'],
            ],
        ])->assertCreated();

        $this->actingAs($hrAdmin)->postJson(route('payroll.employee-salaries.store'), [
            'employee_id' => $dewiEmployee->id,
            'salary_structure_id' => $structureRes->json('data.id'),
            'effective_date' => '2026-10-01',
            'base_salary' => '22000000.00',
            'payment_method' => PaymentMethod::BANK_TRANSFER->value,
        ])->assertCreated();

        // 4. Step C: Employee Records Daily Attendance (1 Present Day + 1 Late Day)
        // IDOR Guard: Colleague cannot clock in on behalf of Dewi
        $this->actingAs($colleagueUser)->postJson(route('attendance.clock-in'), [
            'employee_id' => $dewiEmployee->id,
            'timestamp' => '2026-10-05 08:50:00',
        ])->assertForbidden();

        $this->actingAs($dewiUser)->postJson(route('attendance.clock-in'), [
            'timestamp' => '2026-10-05 08:55:00',
            'location' => '-6.2088,106.8456',
        ])->assertCreated()->assertJsonPath('data.status', 'PRESENT');

        $this->actingAs($dewiUser)->postJson(route('attendance.clock-out'), [
            'timestamp' => '2026-10-05 17:10:00',
            'location' => '-6.2088,106.8456',
        ])->assertOk();

        $this->actingAs($dewiUser)->postJson(route('attendance.clock-in'), [
            'timestamp' => '2026-10-06 09:30:00',
            'location' => '-6.2088,106.8456',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'LATE')
            ->assertJsonPath('data.late_minutes', 30);

        $this->actingAs($dewiUser)->postJson(route('attendance.clock-out'), [
            'timestamp' => '2026-10-06 17:30:00',
            'location' => '-6.2088,106.8456',
        ])->assertOk();

        // 5. Step D: Leave Submission & Multi-Level Manager Approval
        $annualLeave = LeaveType::factory()->create([
            'code' => 'ANNUAL_E2E',
            'name' => 'Annual Paid Leave',
            'default_quota_days' => 12,
            'is_paid' => true,
            'requires_attachment' => false,
        ]);

        $unpaidLeave = LeaveType::factory()->create([
            'code' => 'UNPAID_E2E',
            'name' => 'Unpaid Leave',
            'default_quota_days' => 10,
            'is_paid' => false,
            'requires_attachment' => false,
        ]);

        $annualBalance = LeaveBalance::factory()->create([
            'employee_id' => $dewiEmployee->id,
            'leave_type_id' => $annualLeave->id,
            'year' => 2026,
            'total_quota' => 12,
            'used_quota' => 0,
            'pending_quota' => 0,
        ]);

        LeaveBalance::factory()->create([
            'employee_id' => $dewiEmployee->id,
            'leave_type_id' => $unpaidLeave->id,
            'year' => 2026,
            'total_quota' => 10,
            'used_quota' => 0,
            'pending_quota' => 0,
        ]);

        $paidLeaveRes = $this->actingAs($dewiUser)->postJson(route('attendance.leave-requests.store'), [
            'leave_type_id' => $annualLeave->id,
            'start_date' => '2026-10-12',
            'end_date' => '2026-10-13',
            'reason' => 'Annual family event',
        ])->assertCreated();
        $paidLeaveId = (int) $paidLeaveRes->json('data.id');

        $unpaidLeaveRes = $this->actingAs($dewiUser)->postJson(route('attendance.leave-requests.store'), [
            'leave_type_id' => $unpaidLeave->id,
            'start_date' => '2026-10-15',
            'end_date' => '2026-10-15',
            'reason' => 'Personal errand',
        ])->assertCreated();
        $unpaidLeaveId = (int) $unpaidLeaveRes->json('data.id');

        // IDOR & Self-Approval Guards
        $this->actingAs($dewiUser)
            ->postJson(route('attendance.leave-requests.approve', $paidLeaveId))
            ->assertForbidden();

        $this->actingAs($colleagueUser)
            ->getJson(route('attendance.leave-requests.show', $paidLeaveId))
            ->assertForbidden();

        // Direct Manager Approves Both Leaves
        $this->actingAs($managerUser)
            ->postJson(route('attendance.leave-requests.approve', $paidLeaveId))
            ->assertOk()
            ->assertJsonPath('data.status', 'APPROVED');

        $this->actingAs($managerUser)
            ->postJson(route('attendance.leave-requests.approve', $unpaidLeaveId))
            ->assertOk()
            ->assertJsonPath('data.status', 'APPROVED');

        $annualBalance->refresh();
        $this->assertSame(2, $annualBalance->used_quota);
        $this->assertSame(0, $annualBalance->pending_quota);
        $this->assertSame(10, $annualBalance->remainingQuota());

        // 6. Step E: Monthly Bulk Payroll Calculation & Superadmin Approval
        $batchRes = $this->actingAs($hrAdmin)->postJson(route('payroll.batches.generate'), [
            'payroll_period' => '2026-10',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'title' => 'Payroll October 2026',
        ])->assertCreated();

        $payrollId = (int) $batchRes->json('data.id');

        /** @var Payslip $dewiPayslip */
        $dewiPayslip = Payslip::query()
            ->where('payroll_id', $payrollId)
            ->where('employee_id', $dewiEmployee->id)
            ->firstOrFail();

        // Exact Decimal Math Verification:
        // Basic: 22,000,000.00
        // Allowance: +1,500,000.00 (Meal)
        // Deductions: 300,000.00 (BPJS) + 50,000.00 (1 Late Day) + 1,000,000.00 (1 Unpaid Day = 22M / 22) = 1,350,000.00
        // Net Salary: 22,150,000.00
        $this->assertSame('22000000.00', (string) $dewiPayslip->basic_salary);
        $this->assertSame('1500000.00', (string) $dewiPayslip->total_allowance);
        $this->assertSame('1350000.00', (string) $dewiPayslip->total_deduction);
        $this->assertSame('22150000.00', (string) $dewiPayslip->net_salary);
        $this->assertSame(PayslipStatus::DRAFT, $dewiPayslip->status);

        // Guard: Draft PDF cannot be downloaded yet, and HR Admin cannot approve payroll batch
        $this->actingAs($dewiUser)
            ->get(route('payroll.payslips.pdf', $dewiPayslip->id))
            ->assertForbidden();

        $this->actingAs($hrAdmin)
            ->postJson(route('payroll.batches.approve', $payrollId))
            ->assertForbidden();

        // Superadmin Approves & Publishes Payroll Batch
        $this->actingAs($superadmin)
            ->postJson(route('payroll.batches.approve', $payrollId))
            ->assertOk()
            ->assertJsonPath('data.status', PayrollStatus::APPROVED->value);

        // 7. Step F: Official PDF Download, Colleague IDOR Block & Immutability Enforcement
        $pdfResponse = $this->actingAs($dewiUser)
            ->get(route('payroll.payslips.pdf', $dewiPayslip->id));

        $pdfResponse->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('%PDF-', (string) $pdfResponse->getContent());

        // IDOR Guard: Colleague cannot view or download Dewi's published payslip
        $this->actingAs($colleagueUser)
            ->getJson(route('payroll.payslips.show', $dewiPayslip->id))
            ->assertForbidden();

        $this->actingAs($colleagueUser)
            ->get(route('payroll.payslips.pdf', $dewiPayslip->id))
            ->assertForbidden();

        // Immutability Guard: Direct Eloquent manipulation on APPROVED Payroll or PUBLISHED Payslip throws DomainException
        $lockedPayroll = Payroll::query()->findOrFail($payrollId);
        $dewiPayslip->refresh();

        $payrollMutationBlocked = false;
        try {
            $lockedPayroll->update(['total_net_amount' => '99999999.00']);
        } catch (DomainException) {
            $payrollMutationBlocked = true;
        }
        $this->assertTrue($payrollMutationBlocked, 'Expected DomainException when mutating approved Payroll.');

        $payslipMutationBlocked = false;
        try {
            $dewiPayslip->update(['net_salary' => '99999999.00']);
        } catch (DomainException) {
            $payslipMutationBlocked = true;
        }
        $this->assertTrue($payslipMutationBlocked, 'Expected DomainException when mutating published Payslip.');

        $payslipDeleteBlocked = false;
        try {
            $dewiPayslip->forceDelete();
        } catch (DomainException) {
            $payslipDeleteBlocked = true;
        }
        $this->assertTrue($payslipDeleteBlocked, 'Expected DomainException when force-deleting published Payslip.');

        // Verify Audit Trail Completeness across All Modules
        $recordedEvents = AuditLog::query()->pluck('event')->all();
        $this->assertContains('employee.onboarded', $recordedEvents);
        $this->assertContains('leave_request.submitted', $recordedEvents);
        $this->assertContains('leave_request.approved', $recordedEvents);
        $this->assertContains('payroll.generated', $recordedEvents);
        $this->assertContains('payroll.approved', $recordedEvents);
    }

    public function test_production_demo_seeder_is_idempotent_and_populates_complete_dataset(): void
    {
        $this->seed(DatabaseSeeder::class);
        // Re-run to prove idempotency without duplicate constraint violations
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(4, Department::query()->count());
        $this->assertSame(3, Department::query()->whereNotNull('parent_id')->count());
        $this->assertSame(8, Position::query()->count());

        $this->assertSame(1, User::role(RoleName::SUPERADMIN->value)->count());
        $this->assertSame(1, User::role(RoleName::HR_ADMIN->value)->count());
        $this->assertSame(2, User::role(RoleName::MANAGER->value)->count());
        $this->assertSame(10, User::role(RoleName::EMPLOYEE->value)->count());

        $this->assertSame(12, Employee::query()->count());
        $this->assertGreaterThanOrEqual(24, LeaveBalance::query()->where('year', 2026)->count());
        $this->assertGreaterThanOrEqual(200, Attendance::query()->count());

        $approvedPayroll = Payroll::query()->where('status', PayrollStatus::APPROVED->value)->first();
        $this->assertNotNull($approvedPayroll);
        $this->assertSame(12, $approvedPayroll->payslips()->where('status', PayslipStatus::PUBLISHED->value)->count());
        $this->assertSame(0, LeaveRequest::query()->whereNull('employee_id')->count());
    }
}
