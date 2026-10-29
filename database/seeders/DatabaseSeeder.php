<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\RBAC\Enums\RoleName;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
        ]);

        $superadmin = User::query()->firstOrCreate(
            ['email' => 'admin@hris.local'],
            [
                'name' => 'System Superadmin',
                'password' => 'password',
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );

        if (! $superadmin->hasRole(RoleName::SUPERADMIN->value)) {
            $superadmin->assignRole(RoleName::SUPERADMIN->value);
        }
    }
}
