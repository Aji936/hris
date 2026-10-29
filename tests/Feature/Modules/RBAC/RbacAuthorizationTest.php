<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\RBAC;

use App\Models\User;
use App\Modules\RBAC\Enums\PermissionName;
use App\Modules\RBAC\Enums\RoleName;
use App\Modules\RBAC\Models\AuditLog;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RbacAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_role_and_permission_seeder_populates_baseline_roles_and_granular_permissions(): void
    {
        foreach (RoleName::values() as $roleName) {
            $this->assertDatabaseHas('roles', [
                'name' => $roleName,
                'guard_name' => 'web',
            ]);
        }

        $expectedCorePermissions = [
            PermissionName::EMPLOYEE_VIEW->value,
            PermissionName::EMPLOYEE_CREATE->value,
            PermissionName::ATTENDANCE_VIEW->value,
            PermissionName::LEAVE_APPROVE->value,
            PermissionName::PAYROLL_VIEW->value,
            PermissionName::ROLE_MANAGE->value,
        ];

        foreach ($expectedCorePermissions as $permission) {
            $this->assertDatabaseHas('permissions', [
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $this->assertSame(count(RoleName::cases()), Role::query()->count());
        $this->assertSame(count(PermissionName::cases()), Permission::query()->count());
    }

    public function test_each_baseline_role_grants_expected_granular_permissions(): void
    {
        $hrAdmin = User::factory()->create();
        $hrAdmin->assignRole(RoleName::HR_ADMIN->value);

        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MANAGER->value);

        $employee = User::factory()->create();
        $employee->assignRole(RoleName::EMPLOYEE->value);

        // HR Admin capabilities
        $this->assertTrue($hrAdmin->can(PermissionName::EMPLOYEE_VIEW->value));
        $this->assertTrue($hrAdmin->can(PermissionName::EMPLOYEE_CREATE->value));
        $this->assertTrue($hrAdmin->can(PermissionName::ATTENDANCE_VIEW->value));
        $this->assertTrue($hrAdmin->can(PermissionName::PAYROLL_VIEW->value));
        $this->assertFalse($hrAdmin->can(PermissionName::ROLE_MANAGE->value));

        // Manager capabilities
        $this->assertTrue($manager->can(PermissionName::EMPLOYEE_VIEW->value));
        $this->assertTrue($manager->can(PermissionName::LEAVE_APPROVE->value));
        $this->assertFalse($manager->can(PermissionName::EMPLOYEE_CREATE->value));
        $this->assertFalse($manager->can(PermissionName::PAYROLL_VIEW->value));

        // Employee self-service isolation
        $this->assertTrue($employee->can(PermissionName::EMPLOYEE_VIEW_SELF->value));
        $this->assertTrue($employee->can(PermissionName::LEAVE_CREATE->value));
        $this->assertFalse($employee->can(PermissionName::EMPLOYEE_VIEW->value));
        $this->assertFalse($employee->can(PermissionName::LEAVE_APPROVE->value));
        $this->assertFalse($employee->can(PermissionName::PAYROLL_VIEW->value));
    }

    public function test_superadmin_can_list_roles_and_sync_user_roles_with_audit_trail(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole(RoleName::SUPERADMIN->value);

        $targetUser = User::factory()->create();
        $targetUser->assignRole(RoleName::EMPLOYEE->value);

        $this->actingAs($superadmin)
            ->getJson(route('rbac.roles.index'))
            ->assertOk()
            ->assertJsonCount(4, 'data');

        $this->actingAs($superadmin)
            ->putJson(route('rbac.users.roles.sync', $targetUser), [
                'roles' => [RoleName::MANAGER->value],
            ])
            ->assertOk()
            ->assertJsonPath('data.roles.0', RoleName::MANAGER->value);

        $this->assertTrue($targetUser->fresh()->hasRole(RoleName::MANAGER->value));

        $auditEntry = AuditLog::query()
            ->where('event', 'user.roles_synced')
            ->where('auditable_id', (string) $targetUser->id)
            ->first();

        $this->assertNotNull($auditEntry);
        $this->assertSame($superadmin->id, $auditEntry->actor_id);
        $this->assertSame(['roles' => [RoleName::EMPLOYEE->value]], $auditEntry->old_values);
        $this->assertSame(['roles' => [RoleName::MANAGER->value]], $auditEntry->new_values);
    }

    public function test_unauthorized_roles_receive_403_forbidden_on_protected_rbac_endpoints(): void
    {
        $employee = User::factory()->create();
        $employee->assignRole(RoleName::EMPLOYEE->value);

        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MANAGER->value);

        $targetUser = User::factory()->create();

        // Guest redirected to login
        $this->get(route('rbac.roles.index'))
            ->assertRedirect(route('login'));

        // Employee forbidden from viewing or managing roles
        $this->actingAs($employee)
            ->getJson(route('rbac.roles.index'))
            ->assertForbidden();

        $this->actingAs($employee)
            ->putJson(route('rbac.users.roles.sync', $targetUser), [
                'roles' => [RoleName::SUPERADMIN->value],
            ])
            ->assertForbidden();

        // Manager also forbidden from RBAC role management
        $this->actingAs($manager)
            ->getJson(route('rbac.roles.index'))
            ->assertForbidden();
    }

    public function test_user_policy_enforces_ownership_and_permission_rules(): void
    {
        $employeeA = User::factory()->create();
        $employeeA->assignRole(RoleName::EMPLOYEE->value);

        $employeeB = User::factory()->create();
        $employeeB->assignRole(RoleName::EMPLOYEE->value);

        $hrAdmin = User::factory()->create();
        $hrAdmin->assignRole(RoleName::HR_ADMIN->value);

        // Employee can view/update own account, but cannot view/update/delete other users
        $this->assertTrue($employeeA->can('view', $employeeA));
        $this->assertFalse($employeeA->can('view', $employeeB));
        $this->assertFalse($employeeA->can('delete', $employeeB));

        // HR Admin can view/create/update users, but cannot self-delete or hard-delete without user.delete
        $this->assertTrue($hrAdmin->can('viewAny', User::class));
        $this->assertTrue($hrAdmin->can('view', $employeeB));
        $this->assertTrue($hrAdmin->can('create', User::class));
        $this->assertFalse($hrAdmin->can('delete', $hrAdmin));
    }
}
