<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Policies;

use App\Models\User;
use App\Modules\CoreHR\Models\Position;
use App\Modules\RBAC\Enums\PermissionName;

class PositionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(PermissionName::POSITION_VIEW->value);
    }

    public function view(User $actor, Position $position): bool
    {
        return $actor->can(PermissionName::POSITION_VIEW->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(PermissionName::POSITION_CREATE->value);
    }

    public function update(User $actor, Position $position): bool
    {
        return $actor->can(PermissionName::POSITION_UPDATE->value);
    }

    public function delete(User $actor, Position $position): bool
    {
        return $actor->can(PermissionName::POSITION_DELETE->value);
    }
}
