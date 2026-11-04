<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Policies;

use App\Models\User;
use App\Modules\CoreHR\Models\Department;
use App\Modules\RBAC\Enums\PermissionName;

class DepartmentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(PermissionName::DEPARTMENT_VIEW->value);
    }

    public function view(User $actor, Department $department): bool
    {
        return $actor->can(PermissionName::DEPARTMENT_VIEW->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(PermissionName::DEPARTMENT_CREATE->value);
    }

    public function update(User $actor, Department $department): bool
    {
        return $actor->can(PermissionName::DEPARTMENT_UPDATE->value);
    }

    public function delete(User $actor, Department $department): bool
    {
        return $actor->can(PermissionName::DEPARTMENT_DELETE->value);
    }
}
