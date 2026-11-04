<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\CoreHR;

use App\Models\User;
use App\Modules\CoreHR\Enums\EmploymentStatus;
use App\Modules\CoreHR\Events\EmployeeOnboarded;
use App\Modules\CoreHR\Models\Department;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\Position;
use App\Modules\RBAC\Enums\RoleName;
use App\Modules\RBAC\Models\AuditLog;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_hr_admin_can_onboard_new_employee_creating_user_assigning_role_and_recording_audit_trail(): void
    {
        Event::fake([EmployeeOnboarded::class]);

        $hrAdmin = User::factory()->create();
        $hrAdmin->assignRole(RoleName::HR_ADMIN->value);

        $department = Department::factory()->create(['code' => 'DEPT-ENG', 'name' => 'Engineering']);
        $position = Position::factory()->create([
            'department_id' => $department->id,
            'code' => 'POS-BE-SR',
            'title' => 'Senior Backend Engineer',
            'level' => 'Senior',
        ]);

        $payload = [
            'full_name' => 'Budi Santoso',
            'email' => 'budi.santoso@hris.local',
            'password' => 'SecurePass123!',
            'employee_code' => 'EMP-2026-0001',
            'department_id' => $department->id,
            'position_id' => $position->id,
            'join_date' => '2026-11-01',
            'status' => EmploymentStatus::PROBATION->value,
            'salary_grade' => 'G-4',
            'national_id_number' => '3171010101950001',
            'tax_id_number' => '091234567890000',
            'bank_account_number' => '8877665544',
            'bank_name' => 'BCA',
        ];

        $response = $this->actingAs($hrAdmin)
            ->postJson(route('core-hr.employees.store'), $payload);

        $response->assertCreated()
            ->assertJsonPath('data.employee_code', 'EMP-2026-0001')
            ->assertJsonPath('data.full_name', 'Budi Santoso')
            ->assertJsonPath('data.status', EmploymentStatus::PROBATION->value)
            ->assertJsonPath('data.department.code', 'DEPT-ENG')
            ->assertJsonPath('data.position.code', 'POS-BE-SR');

        $createdUser = User::query()->where('email', 'budi.santoso@hris.local')->first();
        $this->assertNotNull($createdUser);
        $this->assertTrue($createdUser->hasRole(RoleName::EMPLOYEE->value));

        $employee = Employee::query()->where('employee_code', 'EMP-2026-0001')->first();
        $this->assertNotNull($employee);
        $this->assertSame($createdUser->id, $employee->user_id);
        $this->assertSame($hrAdmin->id, $employee->created_by);
        $this->assertSame($hrAdmin->id, $employee->updated_by);

        Event::assertDispatched(EmployeeOnboarded::class, static fn (EmployeeOnboarded $e): bool => $e->employee->id === $employee->id);

        $auditLog = AuditLog::query()
            ->where('event', 'employee.onboarded')
            ->where('auditable_id', (string) $employee->id)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame($hrAdmin->id, $auditLog->actor_id);
        $this->assertSame('[ENCRYPTED]', $auditLog->new_values['national_id_number']);
        $this->assertSame('[ENCRYPTED]', $auditLog->new_values['tax_id_number']);
        $this->assertSame('[ENCRYPTED]', $auditLog->new_values['bank_account_number']);
    }

    public function test_onboarding_can_link_existing_user_and_rejects_duplicate_employee_code(): void
    {
        $hrAdmin = User::factory()->create();
        $hrAdmin->assignRole(RoleName::HR_ADMIN->value);

        $existingUser = User::factory()->create(['name' => 'Siti Rahma']);
        $department = Department::factory()->create();
        $position = Position::factory()->create(['department_id' => $department->id]);

        $this->actingAs($hrAdmin)
            ->postJson(route('core-hr.employees.store'), [
                'user_id' => $existingUser->id,
                'employee_code' => 'EMP-2026-0002',
                'department_id' => $department->id,
                'position_id' => $position->id,
                'join_date' => '2026-11-04',
                'status' => EmploymentStatus::PERMANENT->value,
            ])
            ->assertCreated();

        $this->assertTrue($existingUser->fresh()->hasRole(RoleName::EMPLOYEE->value));

        // Duplicate employee_code must fail validation
        $this->actingAs($hrAdmin)
            ->postJson(route('core-hr.employees.store'), [
                'full_name' => 'Another Person',
                'email' => 'another@hris.local',
                'employee_code' => 'EMP-2026-0002',
                'department_id' => $department->id,
                'position_id' => $position->id,
                'join_date' => '2026-11-04',
                'status' => EmploymentStatus::PERMANENT->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_code']);
    }

    public function test_sensitive_nik_npwp_and_bank_account_are_encrypted_in_database_and_decrypted_via_eloquent(): void
    {
        $rawNik = '3273051208920004';
        $rawNpwp = '082345678912000';
        $rawBank = '1029384756';

        $employee = Employee::factory()->create([
            'national_id_number' => $rawNik,
            'tax_id_number' => $rawNpwp,
            'bank_account_number' => $rawBank,
        ]);

        $rawRow = DB::table('employees')->where('id', $employee->id)->first();
        $this->assertNotNull($rawRow);

        // Stored ciphertext in DB must not equal or contain raw plaintext
        $this->assertNotSame($rawNik, $rawRow->national_id_number);
        $this->assertNotSame($rawNpwp, $rawRow->tax_id_number);
        $this->assertNotSame($rawBank, $rawRow->bank_account_number);
        $this->assertStringNotContainsString($rawNik, (string) $rawRow->national_id_number);
        $this->assertStringNotContainsString($rawNpwp, (string) $rawRow->tax_id_number);

        // Eloquent model must transparently decrypt attributes on read
        $reloaded = Employee::query()->findOrFail($employee->id);
        $this->assertSame($rawNik, $reloaded->national_id_number);
        $this->assertSame($rawNpwp, $reloaded->tax_id_number);
        $this->assertSame($rawBank, $reloaded->bank_account_number);

        // Default toArray() serialization hides sensitive PII
        $serialized = $reloaded->toArray();
        $this->assertArrayNotHasKey('national_id_number', $serialized);
        $this->assertArrayNotHasKey('tax_id_number', $serialized);
        $this->assertArrayNotHasKey('bank_account_number', $serialized);
    }

    public function test_organization_hierarchy_and_manager_subordinate_relationships(): void
    {
        $parentDepartment = Department::factory()->create(['code' => 'DEPT-TECH', 'name' => 'Technology']);
        $childDepartment = Department::factory()->create([
            'parent_id' => $parentDepartment->id,
            'code' => 'DEPT-PLATFORM',
            'name' => 'Platform Engineering',
        ]);

        $leadPosition = Position::factory()->create([
            'department_id' => $childDepartment->id,
            'code' => 'POS-ENG-MGR',
            'title' => 'Engineering Manager',
        ]);
        $staffPosition = Position::factory()->create([
            'department_id' => $childDepartment->id,
            'code' => 'POS-SWE',
            'title' => 'Software Engineer',
        ]);

        $managerEmployee = Employee::factory()->create([
            'department_id' => $childDepartment->id,
            'position_id' => $leadPosition->id,
            'manager_id' => null,
        ]);

        $subordinateA = Employee::factory()->create([
            'department_id' => $childDepartment->id,
            'position_id' => $staffPosition->id,
            'manager_id' => $managerEmployee->id,
        ]);

        $subordinateB = Employee::factory()->create([
            'department_id' => $childDepartment->id,
            'position_id' => $staffPosition->id,
            'manager_id' => $managerEmployee->id,
        ]);

        $this->assertTrue($childDepartment->parent->is($parentDepartment));
        $this->assertCount(1, $parentDepartment->children);
        $this->assertCount(2, $childDepartment->positions);
        $this->assertCount(2, $staffPosition->employees);

        $this->assertTrue($subordinateA->manager->is($managerEmployee));
        $this->assertCount(2, $managerEmployee->subordinates);
        $this->assertTrue($managerEmployee->subordinates->contains($subordinateA));
        $this->assertTrue($managerEmployee->subordinates->contains($subordinateB));
    }

    public function test_update_and_soft_delete_employee_workflow(): void
    {
        $hrAdmin = User::factory()->create();
        $hrAdmin->assignRole(RoleName::HR_ADMIN->value);

        $employee = Employee::factory()->create([
            'status' => EmploymentStatus::PROBATION,
        ]);

        // Prevent assigning self as own manager
        $this->actingAs($hrAdmin)
            ->putJson(route('core-hr.employees.update', $employee), [
                'manager_id' => $employee->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['manager_id']);

        // Promote employee to PERMANENT
        $this->actingAs($hrAdmin)
            ->putJson(route('core-hr.employees.update', $employee), [
                'employee_code' => $employee->employee_code,
                'status' => EmploymentStatus::PERMANENT->value,
                'salary_grade' => 'G-5',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', EmploymentStatus::PERMANENT->value)
            ->assertJsonPath('data.salary_grade', 'G-5');

        // Archive employee (Soft Delete)
        $this->actingAs($hrAdmin)
            ->deleteJson(route('core-hr.employees.destroy', $employee))
            ->assertOk();

        $this->assertSoftDeleted('employees', ['id' => $employee->id]);
    }

    public function test_authorization_enforces_rbac_and_employee_self_service_isolation(): void
    {
        $employeeUserA = User::factory()->create();
        $employeeUserA->assignRole(RoleName::EMPLOYEE->value);
        $employeeRecordA = Employee::factory()->create(['user_id' => $employeeUserA->id]);

        $employeeUserB = User::factory()->create();
        $employeeUserB->assignRole(RoleName::EMPLOYEE->value);
        $employeeRecordB = Employee::factory()->create([
            'user_id' => $employeeUserB->id,
            'national_id_number' => '3175999988880001',
        ]);

        $managerUser = User::factory()->create();
        $managerUser->assignRole(RoleName::MANAGER->value);

        $department = Department::factory()->create();
        $position = Position::factory()->create(['department_id' => $department->id]);

        // Regular employee cannot list directory or create new employees (403 Forbidden)
        $this->actingAs($employeeUserA)
            ->getJson(route('core-hr.employees.index'))
            ->assertForbidden();

        $this->actingAs($employeeUserA)
            ->postJson(route('core-hr.employees.store'), [
                'full_name' => 'Unauthorized',
                'email' => 'unauth@hris.local',
                'employee_code' => 'EMP-9999',
                'department_id' => $department->id,
                'position_id' => $position->id,
                'join_date' => '2026-11-04',
                'status' => EmploymentStatus::PROBATION->value,
            ])
            ->assertForbidden();

        // Regular employee cannot view another employee's record (403), but CAN view own record (200)
        $this->actingAs($employeeUserA)
            ->getJson(route('core-hr.employees.show', $employeeRecordB))
            ->assertForbidden();

        $this->actingAs($employeeUserA)
            ->getJson(route('core-hr.employees.show', $employeeRecordA))
            ->assertOk()
            ->assertJsonPath('data.id', $employeeRecordA->id);

        // Manager can view directory (employee.view), PII of other employees remains hidden, and cannot create employees (403)
        $managerResponse = $this->actingAs($managerUser)
            ->getJson(route('core-hr.employees.show', $employeeRecordB))
            ->assertOk();

        $this->assertArrayNotHasKey('national_id_number', $managerResponse->json('data'));

        $this->actingAs($managerUser)
            ->postJson(route('core-hr.employees.store'), [
                'full_name' => 'Manager Attempt',
                'email' => 'mgr-attempt@hris.local',
                'employee_code' => 'EMP-8888',
                'department_id' => $department->id,
                'position_id' => $position->id,
                'join_date' => '2026-11-04',
                'status' => EmploymentStatus::PROBATION->value,
            ])
            ->assertForbidden();
    }
}
