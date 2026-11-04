<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Contracts;

use App\Modules\Payroll\Models\SalaryStructure;
use App\Shared\Contracts\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends RepositoryInterface<SalaryStructure>
 */
interface SalaryStructureRepositoryInterface extends RepositoryInterface
{
    public function findWithComponents(int|string $id): ?SalaryStructure;

    /**
     * @param  array<int|string, array{default_amount: string}>  $syncPayload
     */
    public function syncComponents(SalaryStructure $structure, array $syncPayload): SalaryStructure;

    /**
     * @return LengthAwarePaginator<SalaryStructure>
     */
    public function paginateStructures(int $perPage = 15): LengthAwarePaginator;
}
