<?php

declare(strict_types=1);

namespace App\Modules\RBAC\Policies;

use App\Models\User;
use App\Modules\RBAC\Enums\PermissionName;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(PermissionName::USER_VIEW->value);
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->id === $target->id || $actor->can(PermissionName::USER_VIEW->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(PermissionName::USER_CREATE->value);
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->id === $target->id || $actor->can(PermissionName::USER_UPDATE->value);
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->id !== $target->id && $actor->can(PermissionName::USER_DELETE->value);
    }
}
