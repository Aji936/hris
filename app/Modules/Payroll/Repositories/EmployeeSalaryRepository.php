<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Repositories;

use App\Modules\Payroll\Contracts\EmployeeSalaryRepositoryInterface;
use App\Modules\Payroll\Models\EmployeeSalary;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<EmployeeSalary>
 */
class EmployeeSalaryRepository extends BaseRepository implements EmployeeSalaryRepositoryInterface
{
    protected function model(): string
    {
        return EmployeeSalary::class;
    }

    public function findActiveForEmployee(int|string $employeeId, bool $lockForUpdate = false): ?EmployeeSalary
    {
        $query = $this->query()
            ->with('salaryStructure.components')
            ->where('employee_id', $employeeId)
            ->where('is_active', true)
            ->orderByDesc('effective_date')
            ->orderByDesc('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        /** @var EmployeeSalary|null $salary */
        $salary = $query->first();

        return $salary;
    }

    public function archiveActiveForEmployee(int|string $employeeId): int
    {
        return $this->query()
            ->where('employee_id', $employeeId)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    public function getHistoryForEmployee(int|string $employeeId): Collection
    {
        return $this->query()
            ->with('salaryStructure.components')
            ->where('employee_id', $employeeId)
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->get();
    }

    public function paginateSalaries(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->with([
                'employee:id,employee_code,full_name,department_id',
                'salaryStructure.components',
            ])
            ->when(
                ! empty($filters['employee_id']),
                static fn (Builder $query) => $query->where('employee_id', $filters['employee_id']),
            )
            ->when(
                isset($filters['is_active']),
                static fn (Builder $query) => $query->where('is_active', (bool) $filters['is_active']),
            )
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
