<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\CoreHR;

use App\Models\User;
use App\Modules\Attendance\Models\LeaveType;
use App\Modules\CoreHR\Enums\EmploymentStatus;
use App\Modules\CoreHR\Enums\MaritalStatus;
use App\Modules\CoreHR\Models\Department;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\Position;
use App\Modules\Payroll\Enums\PaymentMethod;
use App\Modules\Payroll\Enums\PayrollComponentType;
use App\Modules\Payroll\Services\PayrollService;
use App\Modules\Payroll\Services\SalaryAssignmentService;
use App\Modules\RBAC\Enums\RoleName;
use App\Modules\RBAC\Models\AuditLog;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmployeeUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_hr_admin_can_update_employee_organization_profile_and_encrypted_pii_with_audit_log(): void
    {
        $hrAdmin = User::factory()->create();
        $hrAdmin->assignRole(RoleName::HR_ADMIN->value);

        $oldDepartment = Department::factory()->create(['code' => 'ENG-OLD', 'name' => 'Old Engineering']);
        $newDepartment = Department::factory()->create(['code' => 'FIN-NEW', 'name' => 'Finance Division']);

        $oldPosition = Position::factory()->create([
            'department_id' => $oldDepartment->id,
            'code' => 'POS-OLD',
            'title' => 'Junior Developer',
            'level' => 'S1',
        ]);

        $newPosition = Position::factory()->create([
            'department_id' => $newDepartment->id,
            'code' => 'POS-NEW',
            'title' => 'Senior Financial Analyst',
            'level' => 'S3',
        ]);

        $managerEmployee = Employee::factory()->create([
            'employee_code' => 'EMP-MGR-900',
            'full_name' => 'Hendra Kusuma',
            'department_id' => $newDepartment->id,
            'position_id' => $newPosition->id,
        ]);

        $employeeUser = User::factory()->create([
            'name' => 'Andi Pratama',
            'email' => 'andi.old@hris.local',
        ]);
        $employeeUser->assignRole(RoleName::EMPLOYEE->value);

        $employee = Employee::factory()->create([
            'user_id' => $employeeUser->id,
            'employee_code' => 'EMP-UPD-001',
            'full_name' => 'Andi Pratama',
            'phone_number' => '081211112222',
            'address' => 'Jl. Kebon Sirih No. 10, Jakarta Pusat',
            'marital_status' => MaritalStatus::SINGLE,
            'dependents_count' => 0,
            'department_id' => $oldDepartment->id,
            'position_id' => $oldPosition->id,
            'manager_id' => null,
            'status' => EmploymentStatus::PROBATION,
            'salary_grade' => 'S1',
            'national_id_number' => '3171000000000001',
            'tax_id_number' => '091111111111000',
            'bank_account_number' => '111222333444',
            'bank_name' => 'BCA',
        ]);

        // 1. Update organization, user profile, personal family fields, and bank_account_number while leaving national_id_number blank
        $response = $this->actingAs($hrAdmin)->putJson(route('core-hr.employees.update', $employee->id), [
            'employee_code' => 'EMP-UPD-001',
            'full_name' => 'Andi Pratama Putra',
            'email' => 'andi.new@hris.local',
            'phone_number' => '081299998888',
            'address' => 'Jl. Sudirman Kav. 21, Jakarta Selatan',
            'marital_status' => MaritalStatus::MARRIED->value,
            'dependents_count' => 2,
            'department_id' => $newDepartment->id,
            'position_id' => $newPosition->id,
            'manager_id' => $managerEmployee->id,
            'join_date' => '2026-01-10',
            'status' => EmploymentStatus::PERMANENT->value,
            'salary_grade' => 'S3',
            'national_id_number' => '', // Blank should preserve existing encrypted NIK
            'tax_id_number' => '099999999999000',
            'bank_account_number' => '888999000111',
            'bank_name' => 'Bank Mandiri',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.full_name', 'Andi Pratama Putra')
            ->assertJsonPath('data.phone_number', '081299998888')
            ->assertJsonPath('data.address', 'Jl. Sudirman Kav. 21, Jakarta Selatan')
            ->assertJsonPath('data.marital_status', MaritalStatus::MARRIED->value)
            ->assertJsonPath('data.dependents_count', 2)
            ->assertJsonPath('data.status', EmploymentStatus::PERMANENT->value)
            ->assertJsonPath('data.department.id', $newDepartment->id)
            ->assertJsonPath('data.position.id', $newPosition->id)
            ->assertJsonPath('data.manager.id', $managerEmployee->id);

        $employee->refresh();
        $employeeUser->refresh();

        $this->assertSame('Andi Pratama Putra', $employee->full_name);
        $this->assertSame('Andi Pratama Putra', $employeeUser->name);
        $this->assertSame('andi.new@hris.local', $employeeUser->email);
        $this->assertSame('081299998888', $employee->phone_number);
        $this->assertSame('Jl. Sudirman Kav. 21, Jakarta Selatan', $employee->address);
        $this->assertSame(MaritalStatus::MARRIED, $employee->marital_status);
        $this->assertSame(2, $employee->dependents_count);
        $this->assertSame('3171000000000001', $employee->national_id_number);
        $this->assertSame('099999999999000', $employee->tax_id_number);
        $this->assertSame('888999000111', $employee->bank_account_number);
        $this->assertSame('Bank Mandiri', $employee->bank_name);

        // Verify raw database ciphertext encryption
        $rawRow = DB::table('employees')->where('id', $employee->id)->first();
        $this->assertNotNull($rawRow);
        $this->assertNotSame('888999000111', $rawRow->bank_account_number);

        // Verify AuditLog recorded before & after values with masked PII
        $auditLog = AuditLog::query()
            ->where('event', 'employee.updated')
            ->where('auditable_id', (string) $employee->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame($hrAdmin->id, $auditLog->actor_id);
        $this->assertSame($oldDepartment->id, $auditLog->old_values['department_id']);
        $this->assertSame($newDepartment->id, $auditLog->new_values['department_id']);
        $this->assertSame('SINGLE', $auditLog->old_values['marital_status']);
        $this->assertSame('MARRIED', $auditLog->new_values['marital_status']);
        $this->assertSame(0, $auditLog->old_values['dependents_count']);
        $this->assertSame(2, $auditLog->new_values['dependents_count']);
        $this->assertSame('[ENCRYPTED]', $auditLog->new_values['bank_account_number']);

        // Verify invalid marital_status or out-of-range dependents_count is rejected (422)
        $this->actingAs($hrAdmin)->putJson(route('core-hr.employees.update', $employee->id), [
            'marital_status' => 'UNKNOWN_STATUS',
            'dependents_count' => 25,
        ])->assertStatus(422)->assertJsonValidationErrors(['marital_status', 'dependents_count']);

        // Verify Payroll snapshot captures phone_number, address, marital_status, dependents_count, and tax_status
        /** @var PayrollService $payrollService */
        $payrollService = $this->app->make(PayrollService::class);
        $batch = $payrollService->generatePayroll(
            period: '2026-11',
            startDate: '2026-11-01',
            endDate: '2026-11-30',
            adminId: $hrAdmin->id,
        );
        $andiPayslip = $batch->payslips()->where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame('081299998888', $andiPayslip->employee_snapshot['phone_number']);
        $this->assertSame('MARRIED', $andiPayslip->employee_snapshot['marital_status']);
        $this->assertSame(2, $andiPayslip->employee_snapshot['dependents_count']);
        $this->assertSame('K/2', $andiPayslip->employee_snapshot['tax_status']);
    }

    public function test_regular_employee_is_forbidden_from_updating_employee_master_data_or_salaries(): void
    {
        $regularUser = User::factory()->create();
        $regularUser->assignRole(RoleName::EMPLOYEE->value);

        $department = Department::factory()->create();
        $position = Position::factory()->create(['department_id' => $department->id]);
        $targetEmployee = Employee::factory()->create([
            'department_id' => $department->id,
            'position_id' => $position->id,
        ]);

        $this->actingAs($regularUser)
            ->getJson(route('core-hr.employees.edit', $targetEmployee->id))
            ->assertForbidden();

        $this->actingAs($regularUser)
            ->putJson(route('core-hr.employees.update', $targetEmployee->id), [
                'full_name' => 'Unauthorized Name Change',
                'department_id' => $department->id,
                'position_id' => $position->id,
            ])
            ->assertForbidden();

        $this->actingAs($regularUser)
            ->putJson(route('core-hr.departments.update', $department->id), [
                'name' => 'Unauthorized Dept Rename',
            ])
            ->assertForbidden();

        $this->actingAs($regularUser)
            ->putJson(route('core-hr.positions.update', $position->id), [
                'title' => 'Unauthorized Position Rename',
            ])
            ->assertForbidden();
    }

    public function test_hr_admin_can_update_master_departments_positions_leave_types_and_payroll_records(): void
    {
        $hrAdmin = User::factory()->create();
        $hrAdmin->assignRole(RoleName::HR_ADMIN->value);

        $department = Department::factory()->create(['code' => 'OPS', 'name' => 'Operations']);
        $position = Position::factory()->create([
            'department_id' => $department->id,
            'code' => 'POS-OPS-1',
            'title' => 'Operations Staff',
            'level' => 'S1',
        ]);
        $leaveType = LeaveType::factory()->create([
            'code' => 'ANNUAL',
            'name' => 'Annual Leave',
            'default_quota_days' => 12,
        ]);

        $this->actingAs($hrAdmin)
            ->putJson(route('core-hr.departments.update', $department->id), [
                'code' => 'OPS-CORP',
                'name' => 'Corporate Operations',
            ])
            ->assertOk()
            ->assertJsonPath('data.code', 'OPS-CORP');

        $this->actingAs($hrAdmin)
            ->putJson(route('core-hr.positions.update', $position->id), [
                'title' => 'Senior Operations Specialist',
                'level' => 'S2',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Senior Operations Specialist');

        $this->actingAs($hrAdmin)
            ->putJson(route('attendance.leave-types.update', $leaveType->id), [
                'name' => 'Paid Annual Vacation',
                'default_quota_days' => 15,
            ])
            ->assertOk()
            ->assertJsonPath('data.default_quota_days', 15);

        /** @var SalaryAssignmentService $salaryService */
        $salaryService = $this->app->make(SalaryAssignmentService::class);
        $component = $salaryService->createComponent([
            'code' => 'ALLOW_TRANS',
            'name' => 'Transport Allowance',
            'type' => PayrollComponentType::ALLOWANCE->value,
        ]);
        $structure = $salaryService->createStructure([
            'name' => 'Standard Grade S1',
            'components' => [
                ['payroll_component_id' => $component->id, 'default_amount' => '750000.00'],
            ],
        ]);
        $employee = Employee::factory()->create([
            'department_id' => $department->id,
            'position_id' => $position->id,
        ]);
        $initialSalary = $salaryService->assignSalary($employee->id, [
            'salary_structure_id' => $structure->id,
            'effective_date' => '2026-10-01',
            'base_salary' => '10000000.00',
            'payment_method' => PaymentMethod::BANK_TRANSFER->value,
        ]);

        $this->actingAs($hrAdmin)
            ->putJson(route('payroll.components.update', $component->id), [
                'name' => 'Monthly Transport & Fuel Allowance',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Monthly Transport & Fuel Allowance');

        $this->actingAs($hrAdmin)
            ->putJson(route('payroll.structures.update', $structure->id), [
                'name' => 'Updated Grade S2 Package',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Grade S2 Package');

        $this->actingAs($hrAdmin)
            ->putJson(route('payroll.employee-salaries.update', $initialSalary->id), [
                'salary_structure_id' => $structure->id,
                'effective_date' => '2026-11-01',
                'base_salary' => '12500000.00',
                'payment_method' => PaymentMethod::BANK_TRANSFER->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.base_salary', '12500000.00');

        $initialSalary->refresh();
        $this->assertFalse($initialSalary->is_active);
    }
}
