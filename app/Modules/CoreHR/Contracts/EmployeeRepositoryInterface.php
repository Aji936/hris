<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Contracts;

use App\Modules\CoreHR\Models\Employee;
use App\Shared\Contracts\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<Employee>
 */
interface EmployeeRepositoryInterface extends RepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Employee>
     */
    public function paginateDirectory(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findByEmployeeCode(string $employeeCode): ?Employee;

    public function findByUserId(int|string $userId): ?Employee;

    public function findWithRelations(int|string $id): Employee;

    /**
     * @return Collection<int, Employee>
     */
    public function getActiveForPayroll(): Collection;
}
