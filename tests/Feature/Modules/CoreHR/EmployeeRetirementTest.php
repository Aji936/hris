<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\CoreHR;

use App\Models\User;
use App\Modules\CoreHR\Enums\EmploymentStatus;
use App\Modules\CoreHR\Models\Department;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\Position;
use App\Modules\RBAC\Enums\RoleName;
use App\Modules\RBAC\Models\AuditLog;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeRetirementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_retirement_date_is_automatically_calculated_as_55_years_from_date_of_birth_on_create_and_update(): void
    {
        $hrAdmin = User::factory()->create();
        $hrAdmin->assignRole(RoleName::HR_ADMIN->value);

        $department = Department::factory()->create(['code' => 'ENG-RET', 'name' => 'Engineering']);
        $position = Position::factory()->create([
            'department_id' => $department->id,
            'code' => 'POS-RET-1',
            'title' => 'Principal Engineer',
            'level' => 'S4',
        ]);

        // 1. Onboard employee with date_of_birth = 1980-06-15 (retirement_date omitted -> auto 2035-06-15)
        $response = $this->actingAs($hrAdmin)->postJson(route('core-hr.employees.store'), [
            'full_name' => 'gunawan Wibowo',
            'email' => 'gunawan@hris.local',
            'employee_code' => 'EMP-RET-001',
            'date_of_birth' => '1980-06-15',
            'department_id' => $department->id,
            'position_id' => $position->id,
            'join_date' => '2020-01-10',
            'status' => EmploymentStatus::PERMANENT->value,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.date_of_birth', '1980-06-15')
            ->assertJsonPath('data.retirement_date', '2035-06-15');

        $employeeId = (int) $response->json('data.id');
        /** @var Employee $employee */
        $employee = Employee::query()->findOrFail($employeeId);

        $this->assertSame('1980-06-15', $employee->date_of_birth?->toDateString());
        $this->assertSame('2035-06-15', $employee->retirement_date?->toDateString());
        $this->assertNotNull($employee->age);
        $this->assertNotNull($employee->days_until_retirement);

        // 2. Updating date_of_birth without overriding retirement_date recalculates +55 years automatically
        $updateResponse = $this->actingAs($hrAdmin)->putJson(route('core-hr.employees.update', $employee->id), [
            'date_of_birth' => '1982-10-20',
            'retirement_date' => '',
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.date_of_birth', '1982-10-20')
            ->assertJsonPath('data.retirement_date', '2037-10-20');

        // 3. Rejects under-17 date_of_birth with 422 validation error
        $underageDob = CarbonImmutable::now()->subYears(15)->toDateString();
        $this->actingAs($hrAdmin)->putJson(route('core-hr.employees.update', $employee->id), [
            'date_of_birth' => $underageDob,
        ])->assertStatus(422)->assertJsonValidationErrors(['date_of_birth']);
    }

    public function test_scheduled_command_transitions_due_employees_to_retired_status_and_records_audit_log(): void
    {
        $today = CarbonImmutable::now()->startOfDay();

        // Employee whose 55th birthday is today (retirement_date == today)
        $retiringEmployee = Employee::factory()->create([
            'employee_code' => 'EMP-RET-DUE',
            'date_of_birth' => $today->subYears(55)->toDateString(),
            'status' => EmploymentStatus::PERMANENT,
        ]);

        // Employee whose 55th birthday is 60 days in the future (approaching retirement, should remain PERMANENT)
        $approachingEmployee = Employee::factory()->create([
            'employee_code' => 'EMP-RET-SOON',
            'date_of_birth' => $today->subYears(55)->addDays(60)->toDateString(),
            'status' => EmploymentStatus::PERMANENT,
        ]);

        $this->assertSame($today->toDateString(), $retiringEmployee->retirement_date?->toDateString());
        $this->assertSame(0, $retiringEmployee->days_until_retirement);
        $this->assertSame(60, $approachingEmployee->days_until_retirement);

        $this->artisan('hris:check-retiring-employees')
            ->assertExitCode(0);

        $retiringEmployee->refresh();
        $approachingEmployee->refresh();

        $this->assertSame(EmploymentStatus::RETIRED, $retiringEmployee->status);
        $this->assertSame(EmploymentStatus::PERMANENT, $approachingEmployee->status);

        $auditLog = AuditLog::query()
            ->where('event', 'employee.retired')
            ->where('auditable_id', (string) $retiringEmployee->id)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame(EmploymentStatus::PERMANENT->value, $auditLog->old_values['status']);
        $this->assertSame(EmploymentStatus::RETIRED->value, $auditLog->new_values['status']);
    }
}
