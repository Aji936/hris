<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Modules\CoreHR\Contracts\DepartmentRepositoryInterface;
use App\Modules\CoreHR\Contracts\EmployeeRepositoryInterface;
use App\Modules\CoreHR\Contracts\PositionRepositoryInterface;
use App\Modules\CoreHR\Models\Department;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\Position;
use App\Modules\CoreHR\Policies\DepartmentPolicy;
use App\Modules\CoreHR\Policies\EmployeePolicy;
use App\Modules\CoreHR\Policies\PositionPolicy;
use App\Modules\CoreHR\Repositories\DepartmentRepository;
use App\Modules\CoreHR\Repositories\EmployeeRepository;
use App\Modules\CoreHR\Repositories\PositionRepository;
use App\Modules\RBAC\Enums\RoleName;
use App\Modules\RBAC\Policies\RolePolicy;
use App\Modules\RBAC\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DepartmentRepositoryInterface::class, DepartmentRepository::class);
        $this->app->bind(PositionRepositoryInterface::class, PositionRepository::class);
        $this->app->bind(EmployeeRepositoryInterface::class, EmployeeRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(Position::class, PositionPolicy::class);
        Gate::policy(Employee::class, EmployeePolicy::class);

        Gate::before(static function (User $user, string $ability): ?bool {
            if ($user->hasRole(RoleName::SUPERADMIN->value)) {
                return true;
            }

            return null;
        });
    }
}
