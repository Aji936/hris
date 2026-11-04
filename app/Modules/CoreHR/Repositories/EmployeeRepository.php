<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Repositories;

use App\Modules\CoreHR\Contracts\EmployeeRepositoryInterface;
use App\Modules\CoreHR\Models\Employee;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<Employee>
 */
class EmployeeRepository extends BaseRepository implements EmployeeRepositoryInterface
{
    protected function model(): string
    {
        return Employee::class;
    }

    public function paginateDirectory(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->with([
                'user:id,name,email,is_active',
                'department:id,code,name',
                'position:id,code,title,level',
                'manager:id,employee_code,full_name',
            ])
            ->when(
                ! empty($filters['department_id']),
                static fn (Builder $q) => $q->where('department_id', $filters['department_id']),
            )
            ->when(
                ! empty($filters['status']),
                static fn (Builder $q) => $q->where('status', $filters['status']),
            )
            ->when(
                ! empty($filters['search']),
                static function (Builder $q) use ($filters): void {
                    $term = '%'.$filters['search'].'%';
                    $q->where(static function (Builder $sub) use ($term): void {
                        $sub->where('employee_code', 'like', $term)
                            ->orWhere('full_name', 'like', $term);
                    });
                },
            )
            ->orderBy('employee_code')
            ->paginate($perPage);
    }

    public function findByEmployeeCode(string $employeeCode): ?Employee
    {
        /** @var Employee|null $employee */
        $employee = $this->query()->where('employee_code', $employeeCode)->first();

        return $employee;
    }

    public function findByUserId(int|string $userId): ?Employee
    {
        /** @var Employee|null $employee */
        $employee = $this->query()
            ->with(['user', 'department', 'position', 'manager'])
            ->where('user_id', $userId)
            ->first();

        return $employee;
    }

    public function findWithRelations(int|string $id): Employee
    {
        /** @var Employee $employee */
        $employee = $this->query()
            ->with(['user', 'department', 'position', 'manager', 'subordinates'])
            ->findOrFail($id);

        return $employee;
    }
}
