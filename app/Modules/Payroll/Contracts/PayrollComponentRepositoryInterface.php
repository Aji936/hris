<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Contracts;

use App\Modules\Payroll\Models\PayrollComponent;
use App\Shared\Contracts\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends RepositoryInterface<PayrollComponent>
 */
interface PayrollComponentRepositoryInterface extends RepositoryInterface
{
    public function findByCode(string $code): ?PayrollComponent;

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<PayrollComponent>
     */
    public function paginateComponents(array $filters = [], int $perPage = 15): LengthAwarePaginator;
}
