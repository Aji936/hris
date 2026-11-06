<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with baseline RBAC permissions and comprehensive HRIS demo data.
     */
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            ProductionDemoSeeder::class,
        ]);
    }
}
