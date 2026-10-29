<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);

        Gate::before(static function (User $user, string $ability): ?bool {
            if ($user->hasRole(RoleName::SUPERADMIN->value)) {
                return true;
            }

            return null;
        });
    }
}
