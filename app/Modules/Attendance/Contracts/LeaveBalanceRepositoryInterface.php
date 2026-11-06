<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Contracts;

use App\Modules\Attendance\Models\LeaveBalance;
use App\Shared\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<LeaveBalance>
 */
interface LeaveBalanceRepositoryInterface extends RepositoryInterface
{
    public function findForEmployeeYear(
        int|string $employeeId,
        int|string $leaveTypeId,
        int $year,
        bool $lockForUpdate = false,
    ): ?LeaveBalance;

    /**
     * @return Collection<int, LeaveBalance>
     */
    public function getBalancesForEmployeeYear(int|string $employeeId, int $year): Collection;
}
