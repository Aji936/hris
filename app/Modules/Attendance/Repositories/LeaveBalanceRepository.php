<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Repositories;

use App\Modules\Attendance\Contracts\LeaveBalanceRepositoryInterface;
use App\Modules\Attendance\Models\LeaveBalance;
use App\Shared\Repositories\BaseRepository;

/**
 * @extends BaseRepository<LeaveBalance>
 */
class LeaveBalanceRepository extends BaseRepository implements LeaveBalanceRepositoryInterface
{
    protected function model(): string
    {
        return LeaveBalance::class;
    }

    public function findForEmployeeYear(
        int|string $employeeId,
        int|string $leaveTypeId,
        int $year,
        bool $lockForUpdate = false,
    ): ?LeaveBalance {
        $query = $this->query()
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        /** @var LeaveBalance|null $balance */
        $balance = $query->first();

        return $balance;
    }
}
