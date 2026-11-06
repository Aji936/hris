<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Contracts;

use App\Modules\Payroll\Models\Payroll;
use App\Shared\Contracts\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends RepositoryInterface<Payroll>
 */
interface PayrollRepositoryInterface extends RepositoryInterface
{
    public function findByPeriod(string $period, bool $lockForUpdate = false): ?Payroll;

    public function findWithPayslips(int|string $id, bool $lockForUpdate = false): ?Payroll;

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Payroll>
     */
    public function paginateBatches(array $filters = [], int $perPage = 15): LengthAwarePaginator;
}
