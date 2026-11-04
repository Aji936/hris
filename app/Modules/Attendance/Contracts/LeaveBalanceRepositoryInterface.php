<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Contracts;

use App\Modules\Attendance\Models\LeaveBalance;
use App\Shared\Contracts\RepositoryInterface;

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
}
