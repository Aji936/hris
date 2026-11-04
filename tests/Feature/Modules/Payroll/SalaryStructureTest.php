<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Payroll;

use App\Models\User;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\Payroll\Enums\PaymentMethod;
use App\Modules\Payroll\Enums\PayrollComponentType;
use App\Modules\Payroll\Models\EmployeeSalary;
use App\Modules\Payroll\Models\PayrollComponent;
use App\Modules\Payroll\Services\SalaryAssignmentService;
use App\Modules\RBAC\Enums\PermissionName;
use App\Modules\RBAC\Enums\RoleName;
use App\Modules\RBAC\Models\AuditLog;
use App\Shared\Exceptions\DomainException;
use App\Shared\Support\MoneyHelper;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SalaryStructureTest extends TestCase
{
    use RefreshDatabase;

    private SalaryAssignmentService $salaryAssignmentService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->salaryAssignmentService = $this->app->make(SalaryAssignmentService::class);
    }

    public function test_assign_salary_structure_and_archive_previous_salary_on_increment(): void
    {
        $hrAdmin = User::factory()->create();
        $hrAdmin->assignRole(RoleName::HR_ADMIN->value);
        $this->actingAs($hrAdmin);

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

        $juniorStructure = $this->salaryAssignmentService->createStructure([
            'name' => 'Engineering Staff Grade I',
            'description' => 'Standard engineering staff compensation package',
            'components' => [
                ['payroll_component_id' => $mealAllowance->id, 'default_amount' => '1100000.00'],
                ['payroll_component_id' => $bpjsTk->id, 'default_amount' => '240000.00'],
            ],
        ]);

        $seniorStructure = $this->salaryAssignmentService->createStructure([
            'name' => 'Senior Engineering Grade II',
            'description' => 'Senior engineering compensation package',
            'components' => [
                ['payroll_component_id' => $mealAllowance->id, 'default_amount' => '1500000.00'],
                ['payroll_component_id' => $bpjsTk->id, 'default_amount' => '360000.00'],
            ],
        ]);

        $employee = Employee::factory()->create(['basic_salary' => '10000000.00']);

        // 1. Initial onboarding salary assignment
        $initialSalary = $this->salaryAssignmentService->assignSalary($employee->id, [
            'salary_structure_id' => $juniorStructure->id,
            'effective_date' => '2026-01-01',
            'base_salary' => '12000000.00',
            'payment_method' => PaymentMethod::BANK_TRANSFER,
        ]);

        $this->assertTrue($initialSalary->is_active);
        $this->assertSame('12000000.00', $initialSalary->base_salary);
        $this->assertSame('12000000.00', $employee->fresh()?->basic_salary);

        // 2. Backdated salary assignment earlier than active effective_date must be rejected
        try {
            $this->salaryAssignmentService->assignSalary($employee->id, [
                'salary_structure_id' => $seniorStructure->id,
                'effective_date' => '2025-12-01',
                'base_salary' => '18000000.00',
            ]);
            $this->fail('Expected DomainException for backdated salary effective_date was not thrown.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('cannot be earlier', $exception->getMessage());
        }

        // 3. Salary increment & promotion archives previous salary and activates new salary
        $incrementedSalary = $this->salaryAssignmentService->assignSalary($employee->id, [
            'salary_structure_id' => $seniorStructure->id,
            'effective_date' => '2026-07-01',
            'base_salary' => '18000000.00',
            'payment_method' => PaymentMethod::BANK_TRANSFER,
        ]);

        $this->assertFalse($initialSalary->fresh()->is_active);
        $this->assertNull($initialSalary->fresh()->deleted_at);
        $this->assertTrue($incrementedSalary->is_active);
        $this->assertSame($incrementedSalary->id, $employee->fresh()?->activeSalary?->id);
        $this->assertSame('18000000.00', $employee->fresh()?->basic_salary);
        $this->assertCount(2, $employee->fresh()->salaries);

        $auditLog = AuditLog::query()
            ->where('event', 'payroll.salary_incremented')
            ->where('auditable_id', (string) $incrementedSalary->id)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame($initialSalary->id, $auditLog->old_values['archived_salary_id']);
        $this->assertSame('12000000.00', $auditLog->old_values['previous_base_salary']);
        $this->assertSame('18000000.00', $auditLog->new_values['base_salary']);
    }

    public function test_decimal_15_2_precision_integrity_without_float_drift(): void
    {
        $allowanceA = PayrollComponent::factory()->create([
            'code' => 'TRANSPORT_ALLOWANCE',
            'type' => PayrollComponentType::ALLOWANCE,
        ]);
        $allowanceB = PayrollComponent::factory()->create([
            'code' => 'HOUSING_ALLOWANCE',
            'type' => PayrollComponentType::ALLOWANCE,
        ]);
        $deductionPph = PayrollComponent::factory()->create([
            'code' => 'PPH21',
            'type' => PayrollComponentType::DEDUCTION,
        ]);

        $structure = $this->salaryAssignmentService->createStructure([
            'name' => 'Executive Precision Package',
            'components' => [
                ['payroll_component_id' => $allowanceA->id, 'default_amount' => '1250549.25'],
                ['payroll_component_id' => $allowanceB->id, 'default_amount' => '3749450.75'],
                ['payroll_component_id' => $deductionPph->id, 'default_amount' => '987654.33'],
            ],
        ]);

        $this->assertSame('5000000.00', $structure->calculateDefaultComponentTotal(PayrollComponentType::ALLOWANCE));
        $this->assertSame('987654.33', $structure->calculateDefaultComponentTotal(PayrollComponentType::DEDUCTION));

        $employee = Employee::factory()->create();
        $assigned = $this->salaryAssignmentService->assignSalary($employee->id, [
            'salary_structure_id' => $structure->id,
            'effective_date' => '2026-11-01',
            'base_salary' => '18750450.75',
            'payment_method' => PaymentMethod::BANK_TRANSFER,
        ]);

        $reloaded = EmployeeSalary::query()->findOrFail($assigned->id);
        $this->assertSame('18750450.75', $reloaded->base_salary);

        $grossFixed = MoneyHelper::add(
            $reloaded->base_salary,
            $structure->calculateDefaultComponentTotal(PayrollComponentType::ALLOWANCE),
        );
        $netEstimated = MoneyHelper::sub(
            $grossFixed,
            $structure->calculateDefaultComponentTotal(PayrollComponentType::DEDUCTION),
        );

        $this->assertSame('23750450.75', $grossFixed);
        $this->assertSame('22762796.42', $netEstimated);
    }

    public function test_payroll_endpoints_block_regular_employees_and_managers_while_allowing_hr_and_finance(): void
    {
        $employeeUser = User::factory()->create();
        $employeeUser->assignRole(RoleName::EMPLOYEE->value);
        $targetEmployee = Employee::factory()->create(['user_id' => $employeeUser->id]);

        $managerUser = User::factory()->create();
        $managerUser->assignRole(RoleName::MANAGER->value);

        $hrAdminUser = User::factory()->create();
        $hrAdminUser->assignRole(RoleName::HR_ADMIN->value);

        // Custom Finance role granted payroll permissions
        $financeRole = Role::query()->create(['name' => 'Finance', 'guard_name' => 'web']);
        $financeRole->givePermissionTo([
            PermissionName::PAYROLL_VIEW->value,
            PermissionName::PAYROLL_CREATE->value,
        ]);
        $financeUser = User::factory()->create();
        $financeUser->assignRole($financeRole);

        // 1. Regular Employee is strictly blocked (403 Forbidden) on all payroll master/salary endpoints
        $this->actingAs($employeeUser)
            ->getJson(route('payroll.components.index'))
            ->assertForbidden();

        $this->actingAs($employeeUser)
            ->postJson(route('payroll.components.store'), [
                'code' => 'UNAUTHORIZED_COMP',
                'name' => 'Unauthorized',
                'type' => PayrollComponentType::ALLOWANCE->value,
            ])
            ->assertForbidden();

        $this->actingAs($employeeUser)
            ->getJson(route('payroll.structures.index'))
            ->assertForbidden();

        $this->actingAs($employeeUser)
            ->getJson(route('payroll.employee-salaries.index'))
            ->assertForbidden();

        // 2. Department Manager is also blocked (403 Forbidden) from Payroll administration
        $this->actingAs($managerUser)
            ->getJson(route('payroll.employee-salaries.index'))
            ->assertForbidden();

        // 3. HR Admin creates component & salary structure via HTTP (201 Created)
        $compResponse = $this->actingAs($hrAdminUser)
            ->postJson(route('payroll.components.store'), [
                'code' => 'OVERTIME',
                'name' => 'Overtime Pay',
                'type' => PayrollComponentType::ALLOWANCE->value,
                'is_taxable' => true,
                'is_fixed' => false,
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'OVERTIME');

        $componentId = (int) $compResponse->json('data.id');

        $structResponse = $this->actingAs($hrAdminUser)
            ->postJson(route('payroll.structures.store'), [
                'name' => 'Standard Operations Structure',
                'components' => [
                    [
                        'payroll_component_id' => $componentId,
                        'default_amount' => '250000.50',
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.default_allowance_total', '250000.50');

        $structureId = (int) $structResponse->json('data.id');

        // 4. Negative salary or >2 decimal places is rejected by FormRequest validation (422)
        $this->actingAs($financeUser)
            ->postJson(route('payroll.employee-salaries.store'), [
                'employee_id' => $targetEmployee->id,
                'salary_structure_id' => $structureId,
                'effective_date' => '2026-11-01',
                'base_salary' => '-500000.00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['base_salary']);

        // 5. Finance role user assigns valid employee salary (201 Created) and reads exact DECIMAL string
        $this->actingAs($financeUser)
            ->postJson(route('payroll.employee-salaries.store'), [
                'employee_id' => $targetEmployee->id,
                'salary_structure_id' => $structureId,
                'effective_date' => '2026-11-01',
                'base_salary' => '14500750.25',
                'payment_method' => PaymentMethod::BANK_TRANSFER->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.base_salary', '14500750.25')
            ->assertJsonPath('data.is_active', true);

        $this->actingAs($hrAdminUser)
            ->getJson(route('payroll.employee-salaries.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
