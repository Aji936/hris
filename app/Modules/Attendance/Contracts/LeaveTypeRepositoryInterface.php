<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Contracts;

use App\Modules\Attendance\Models\LeaveType;
use App\Shared\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<LeaveType>
 */
interface LeaveTypeRepositoryInterface extends RepositoryInterface
{
    public function findByCode(string $code): ?LeaveType;

    /**
     * @return Collection<int, LeaveType>
     */
    public function getActiveTypes(): Collection;
}
