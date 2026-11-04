<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Policies;

use App\Models\User;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\RBAC\Enums\PermissionName;

class EmployeePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(PermissionName::EMPLOYEE_VIEW->value);
    }

    public function view(User $actor, Employee $employee): bool
    {
        if ($actor->can(PermissionName::EMPLOYEE_VIEW->value)) {
            return true;
        }

        return $actor->can(PermissionName::EMPLOYEE_VIEW_SELF->value)
            && (int) $employee->user_id === (int) $actor->id;
    }

    public function create(User $actor): bool
    {
        return $actor->can(PermissionName::EMPLOYEE_CREATE->value);
    }

    public function update(User $actor, Employee $employee): bool
    {
        return $actor->can(PermissionName::EMPLOYEE_UPDATE->value);
    }

    public function delete(User $actor, Employee $employee): bool
    {
        return (int) $employee->user_id !== (int) $actor->id
            && $actor->can(PermissionName::EMPLOYEE_DELETE->value);
    }
}
