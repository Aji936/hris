<?php

declare(strict_types=1);

namespace App\Modules\RBAC\Policies;

use App\Models\User;
use App\Modules\RBAC\Enums\PermissionName;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(PermissionName::ROLE_VIEW->value);
    }

    public function view(User $actor, Role $role): bool
    {
        return $actor->can(PermissionName::ROLE_VIEW->value);
    }

    public function manage(User $actor): bool
    {
        return $actor->can(PermissionName::ROLE_MANAGE->value);
    }
}
