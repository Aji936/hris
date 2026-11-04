<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Contracts;

use App\Modules\CoreHR\Models\Department;
use App\Shared\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<Department>
 */
interface DepartmentRepositoryInterface extends RepositoryInterface
{
    /**
     * @return Collection<int, Department>
     */
    public function allWithHierarchy(): Collection;

    public function findByCode(string $code): ?Department;
}
