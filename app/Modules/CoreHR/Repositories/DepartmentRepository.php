<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Repositories;

use App\Modules\CoreHR\Contracts\DepartmentRepositoryInterface;
use App\Modules\CoreHR\Models\Department;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Department>
 */
class DepartmentRepository extends BaseRepository implements DepartmentRepositoryInterface
{
    protected function model(): string
    {
        return Department::class;
    }

    public function allWithHierarchy(): Collection
    {
        return $this->query()
            ->with(['parent:id,code,name', 'children:id,parent_id,code,name'])
            ->orderBy('name')
            ->get();
    }

    public function findByCode(string $code): ?Department
    {
        /** @var Department|null $department */
        $department = $this->query()->where('code', $code)->first();

        return $department;
    }
}
