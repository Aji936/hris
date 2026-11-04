<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Contracts;

use App\Modules\Payroll\Models\EmployeeSalary;
use App\Shared\Contracts\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<EmployeeSalary>
 */
interface EmployeeSalaryRepositoryInterface extends RepositoryInterface
{
    public function findActiveForEmployee(int|string $employeeId, bool $lockForUpdate = false): ?EmployeeSalary;

    public function archiveActiveForEmployee(int|string $employeeId): int;

    /**
     * @return Collection<int, EmployeeSalary>
     */
    public function getHistoryForEmployee(int|string $employeeId): Collection;

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<EmployeeSalary>
     */
    public function paginateSalaries(array $filters = [], int $perPage = 15): LengthAwarePaginator;
}
