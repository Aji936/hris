<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Payroll;

use App\Models\User;
use App\Modules\CoreHR\Enums\EmploymentStatus;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\Payroll\Enums\PaymentMethod;
use App\Modules\Payroll\Enums\PayrollComponentType;
use App\Modules\Payroll\Services\PayrollService;
use App\Modules\Payroll\Services\SalaryAssignmentService;
use App\Modules\RBAC\Enums\PermissionName;
use App\Modules\RBAC\Enums\RoleName;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PayslipExportTest extends TestCase
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

    public function test_legitimate_owner_can_download_published_payslip_pdf_with_valid_pdf_header(): void
    {
        $hrAdmin = User::factory()->create();
        $hrAdmin->assignRole(RoleName::HR_ADMIN->value);

        $superadmin = User::factory()->create();
        $superadmin->assignRole(RoleName::SUPERADMIN->value);

        $ownerUser = User::factory()->create();
        $ownerUser->assignRole(RoleName::EMPLOYEE->value);
        $ownerEmployee = Employee::factory()->create([
            'user_id' => $ownerUser->id,
            'employee_code' => 'EMP-PDF-001',
            'full_name' => 'Budi Santoso',
            'status' => EmploymentStatus::PERMANENT,
        ]);

        $mealAllowance = $this->salaryAssignmentService->createComponent([
            'code' => 'MEAL_ALLOWANCE',
            'name' => 'Tunjangan Makan',
            'type' => PayrollComponentType::ALLOWANCE,
        ]);
        $bpjs = $this->salaryAssignmentService->createComponent([
            'code' => 'BPJS_KES',
            'name' => 'BPJS Kesehatan',
            'type' => PayrollComponentType::DEDUCTION,
        ]);
        $structure = $this->salaryAssignmentService->createStructure([
            'name' => 'PDF Package',
            'components' => [
                ['payroll_component_id' => $mealAllowance->id, 'default_amount' => '1200000.00'],
                ['payroll_component_id' => $bpjs->id, 'default_amount' => '150000.00'],
            ],
        ]);
        $this->salaryAssignmentService->assignSalary($ownerEmployee->id, [
            'salary_structure_id' => $structure->id,
            'effective_date' => '2026-11-01',
            'base_salary' => '15000000.00',
            'payment_method' => PaymentMethod::BANK_TRANSFER,
        ]);

        $payroll = $this->payrollService->generatePayroll(
            period: '2026-11',
            startDate: '2026-11-01',
            endDate: '2026-11-30',
            adminId: $hrAdmin->id,
        );
        $this->payrollService->approvePayroll($payroll->id, $superadmin->id);

        $payslip = $payroll->payslips()->firstOrFail();

        $response = $this->actingAs($ownerUser)
            ->get(route('payroll.payslips.pdf', $payslip->id));

        $response->assertOk();
        $this->assertStringContainsString(
            'application/pdf',
            (string) $response->headers->get('Content-Type'),
        );
        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
    }

    public function test_other_employee_receives_403_forbidden_when_downloading_colleague_payslip_pdf(): void
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
            'basic_salary' => '12000000.00',
        ]);

        $userB = User::factory()->create();
        $userB->assignRole(RoleName::EMPLOYEE->value);
        Employee::factory()->create([
            'user_id' => $userB->id,
            'status' => EmploymentStatus::PERMANENT,
            'basic_salary' => '12000000.00',
        ]);

        $payroll = $this->payrollService->generatePayroll(
            period: '2026-11',
            startDate: '2026-11-01',
            endDate: '2026-11-30',
            adminId: $hrAdmin->id,
        );
        $this->payrollService->approvePayroll($payroll->id, $superadmin->id);

        $payslipA = $payroll->payslips()->where('employee_id', $employeeA->id)->firstOrFail();

        // Employee B tries to download Employee A's PDF payslip -> 403 Forbidden
        $this->actingAs($userB)
            ->get(route('payroll.payslips.pdf', $payslipA->id))
            ->assertForbidden();
    }

    public function test_downloading_draft_payslip_pdf_is_rejected_with_403_forbidden(): void
    {
        $hrAdmin = User::factory()->create();
        $hrAdmin->assignRole(RoleName::HR_ADMIN->value);

        $ownerUser = User::factory()->create();
        $ownerUser->assignRole(RoleName::EMPLOYEE->value);
        $ownerEmployee = Employee::factory()->create([
            'user_id' => $ownerUser->id,
            'status' => EmploymentStatus::PERMANENT,
            'basic_salary' => '10000000.00',
        ]);

        // Generate payroll WITHOUT approving -> payslips remain in DRAFT status
        $payroll = $this->payrollService->generatePayroll(
            period: '2026-11',
            startDate: '2026-11-01',
            endDate: '2026-11-30',
            adminId: $hrAdmin->id,
        );

        $draftPayslip = $payroll->payslips()->where('employee_id', $ownerEmployee->id)->firstOrFail();

        // Both Employee owner and HR Admin receive 403 Forbidden while payslip is still DRAFT
        $this->actingAs($ownerUser)
            ->get(route('payroll.payslips.pdf', $draftPayslip->id))
            ->assertForbidden();

        $this->actingAs($hrAdmin)
            ->get(route('payroll.payslips.pdf', $draftPayslip->id))
            ->assertForbidden();
    }

    public function test_monthly_payroll_csv_export_is_restricted_to_hr_admin_finance_and_superadmin(): void
    {
        $hrAdmin = User::factory()->create();
        $hrAdmin->assignRole(RoleName::HR_ADMIN->value);

        $superadmin = User::factory()->create();
        $superadmin->assignRole(RoleName::SUPERADMIN->value);

        $financeRole = Role::query()->create(['name' => 'Finance', 'guard_name' => 'web']);
        $financeRole->givePermissionTo([PermissionName::PAYROLL_VIEW->value]);
        $financeUser = User::factory()->create();
        $financeUser->assignRole($financeRole);

        $managerUser = User::factory()->create();
        $managerUser->assignRole(RoleName::MANAGER->value);

        $employeeUser = User::factory()->create();
        $employeeUser->assignRole(RoleName::EMPLOYEE->value);
        Employee::factory()->create([
            'user_id' => $employeeUser->id,
            'employee_code' => 'EMP-CSV-001',
            'full_name' => 'Dewi Lestari',
            'status' => EmploymentStatus::PERMANENT,
            'basic_salary' => '13500000.50',
        ]);

        $payroll = $this->payrollService->generatePayroll(
            period: '2026-11',
            startDate: '2026-11-01',
            endDate: '2026-11-30',
            adminId: $hrAdmin->id,
        );

        // Regular Employee and Manager are strictly forbidden (403)
        $this->actingAs($employeeUser)
            ->get(route('payroll.batches.export-csv', $payroll->id))
            ->assertForbidden();

        $this->actingAs($managerUser)
            ->get(route('payroll.batches.export-csv', $payroll->id))
            ->assertForbidden();

        // HR Admin, Finance, and Superadmin can export CSV report
        foreach ([$hrAdmin, $financeUser, $superadmin] as $authorizedActor) {
            $csvResponse = $this->actingAs($authorizedActor)
                ->get(route('payroll.batches.export-csv', $payroll->id));

            $csvResponse->assertOk();
            $this->assertStringContainsString(
                'text/csv',
                (string) $csvResponse->headers->get('Content-Type'),
            );

            $csvContent = $csvResponse->streamedContent();
            $this->assertStringContainsString('EMP-CSV-001', $csvContent);
            $this->assertStringContainsString('Dewi Lestari', $csvContent);
            $this->assertStringContainsString('13500000.50', $csvContent);
            $this->assertStringContainsString('TOTAL,2026-11', $csvContent);
        }
    }
}
