<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Contracts;

use App\Modules\Attendance\Models\LeaveType;
use App\Shared\Contracts\RepositoryInterface;

/**
 * @extends RepositoryInterface<LeaveType>
 */
interface LeaveTypeRepositoryInterface extends RepositoryInterface
{
    public function findByCode(string $code): ?LeaveType;
}
