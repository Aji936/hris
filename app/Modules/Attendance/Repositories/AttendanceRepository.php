<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Repositories;

use App\Modules\Attendance\Contracts\AttendanceRepositoryInterface;
use App\Modules\Attendance\Models\Attendance;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Attendance>
 */
class AttendanceRepository extends BaseRepository implements AttendanceRepositoryInterface
{
    protected function model(): string
    {
        return Attendance::class;
    }

    public function findByEmployeeAndDate(int|string $employeeId, string $date, bool $lockForUpdate = false): ?Attendance
    {
        $query = $this->query()
            ->with('shift')
            ->where('employee_id', $employeeId)
            ->whereDate('date', $date);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        /** @var Attendance|null $attendance */
        $attendance = $query->first();

        return $attendance;
    }

    public function findLatestOpenForEmployee(int|string $employeeId, bool $lockForUpdate = false): ?Attendance
    {
        $query = $this->query()
            ->with('shift')
            ->where('employee_id', $employeeId)
            ->whereNotNull('clock_in')
            ->whereNull('clock_out')
            ->orderByDesc('date');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        /** @var Attendance|null $attendance */
        $attendance = $query->first();

        return $attendance;
    }

    public function getForEmployeeBetweenDates(int|string $employeeId, string $startDate, string $endDate): Collection
    {
        return $this->query()
            ->with('shift')
            ->where('employee_id', $employeeId)
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->orderByDesc('date')
            ->get();
    }

    public function paginateRecords(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->with([
                'employee:id,user_id,employee_code,full_name,department_id',
                'shift:id,name,start_time,end_time,late_grace_period_minutes',
            ])
            ->when(
                ! empty($filters['employee_id']),
                static fn (Builder $q) => $q->where('employee_id', $filters['employee_id']),
            )
            ->when(
                ! empty($filters['date']),
                static fn (Builder $q) => $q->whereDate('date', $filters['date']),
            )
            ->when(
                ! empty($filters['status']),
                static fn (Builder $q) => $q->where('status', $filters['status']),
            )
            ->orderByDesc('date')
            ->orderByDesc('clock_in')
            ->paginate($perPage);
    }
}
