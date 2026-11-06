<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Policies;

use App\Models\User;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\RBAC\Enums\PermissionName;
use App\Modules\RBAC\Enums\RoleName;
use Illuminate\Database\Eloquent\Model;

class PayrollPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(PermissionName::PAYROLL_VIEW->value);
    }

    public function view(User $actor, Model $record): bool
    {
        return $actor->can(PermissionName::PAYROLL_VIEW->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(PermissionName::PAYROLL_CREATE->value);
    }

    public function update(User $actor, Model $record): bool
    {
        return $actor->can(PermissionName::PAYROLL_CREATE->value);
    }

    public function approve(User $actor, Payroll $payroll): bool
    {
        return $actor->hasRole(RoleName::SUPERADMIN->value)
            || $actor->hasRole('Finance Director')
            || $actor->can(PermissionName::PAYROLL_APPROVE->value);
    }
}
