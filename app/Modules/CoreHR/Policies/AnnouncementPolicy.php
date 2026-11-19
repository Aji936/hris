<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Policies;

use App\Models\User;
use App\Modules\CoreHR\Models\Announcement;
use App\Modules\RBAC\Enums\PermissionName;
use App\Modules\RBAC\Enums\RoleName;

class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->id > 0;
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return $user->id > 0 && ($announcement->is_active || $this->isHrOrSuperadmin($user));
    }

    public function create(User $user): bool
    {
        return $this->isHrOrSuperadmin($user);
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $this->isHrOrSuperadmin($user);
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $this->isHrOrSuperadmin($user);
    }

    private function isHrOrSuperadmin(User $user): bool
    {
        return $user->hasAnyRole([
            RoleName::SUPERADMIN->value,
            RoleName::HR_ADMIN->value,
        ]) || $user->can(PermissionName::EMPLOYEE_CREATE->value);
    }
}
