<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\RBAC\Enums\PermissionName;
use App\Modules\RBAC\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionName::cases() as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission->value,
                'guard_name' => 'web',
            ]);
        }

        foreach (RoleName::cases() as $roleEnum) {
            /** @var Role $role */
            $role = Role::query()->firstOrCreate([
                'name' => $roleEnum->value,
                'guard_name' => 'web',
            ]);

            $permissionValues = array_map(
                static fn (PermissionName $permission): string => $permission->value,
                $roleEnum->defaultPermissions(),
            );

            $role->syncPermissions($permissionValues);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
