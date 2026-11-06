<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Contracts;

use App\Modules\Attendance\Models\Attendance;
use App\Shared\Contracts\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<Attendance>
 */
interface AttendanceRepositoryInterface extends RepositoryInterface
{
    public function findByEmployeeAndDate(int|string $employeeId, string $date, bool $lockForUpdate = false): ?Attendance;

    public function findLatestOpenForEmployee(int|string $employeeId, bool $lockForUpdate = false): ?Attendance;

    /**
     * @return Collection<int, Attendance>
     */
    public function getForEmployeeBetweenDates(int|string $employeeId, string $startDate, string $endDate): Collection;

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Attendance>
     */
    public function paginateRecords(array $filters = [], int $perPage = 15): LengthAwarePaginator;
}
