<?php

declare(strict_types=1);

namespace App\Modules\RBAC\Services;

use App\Models\User;
use App\Modules\RBAC\Enums\RoleName;
use App\Modules\RBAC\Repositories\RoleRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class RoleManagementService
{
    public function __construct(
        private readonly RoleRepository $roleRepository,
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @return Collection<int, Role>
     */
    public function listRolesWithPermissions(): Collection
    {
        return $this->roleRepository->allWithPermissions();
    }

    /**
     * Synchronize roles for a user within a transaction and record an audit log.
     *
     * @param  list<RoleName|string>  $roles
     */
    public function syncUserRoles(User $targetUser, array $roles): User
    {
        $normalizedRoles = array_values(array_map(
            static fn (RoleName|string $role): string => $role instanceof RoleName ? $role->value : $role,
            $roles,
        ));

        return DB::transaction(function () use ($targetUser, $normalizedRoles): User {
            $previousRoles = $targetUser->getRoleNames()->values()->all();

            $targetUser->syncRoles($normalizedRoles);

            $this->auditLogService->record(
                event: 'user.roles_synced',
                auditable: $targetUser,
                oldValues: ['roles' => $previousRoles],
                newValues: ['roles' => $targetUser->getRoleNames()->values()->all()],
            );

            return $targetUser->load('roles');
        });
    }
}
