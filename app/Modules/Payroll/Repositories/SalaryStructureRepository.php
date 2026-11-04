<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Repositories;

use App\Modules\Payroll\Contracts\SalaryStructureRepositoryInterface;
use App\Modules\Payroll\Models\SalaryStructure;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends BaseRepository<SalaryStructure>
 */
class SalaryStructureRepository extends BaseRepository implements SalaryStructureRepositoryInterface
{
    protected function model(): string
    {
        return SalaryStructure::class;
    }

    public function findWithComponents(int|string $id): ?SalaryStructure
    {
        /** @var SalaryStructure|null $structure */
        $structure = $this->query()
            ->with('components')
            ->whereKey($id)
            ->first();

        return $structure;
    }

    public function syncComponents(SalaryStructure $structure, array $syncPayload): SalaryStructure
    {
        $structure->components()->sync($syncPayload);

        return $structure->load('components');
    }

    public function paginateStructures(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->with('components')
            ->orderBy('name')
            ->paginate($perPage);
    }
}
