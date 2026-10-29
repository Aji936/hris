<?php

declare(strict_types=1);

namespace App\Modules\RBAC\Repositories;

use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role;

/**
 * @extends BaseRepository<Role>
 */
class RoleRepository extends BaseRepository
{
    protected function model(): string
    {
        return Role::class;
    }

    /**
     * @return Collection<int, Role>
     */
    public function allWithPermissions(): Collection
    {
        return $this->query()
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get();
    }
}
