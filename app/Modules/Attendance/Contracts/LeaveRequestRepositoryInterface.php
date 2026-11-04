<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Contracts;

use App\Models\User;
use App\Modules\Attendance\Models\LeaveRequest;
use App\Shared\Contracts\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends RepositoryInterface<LeaveRequest>
 */
interface LeaveRequestRepositoryInterface extends RepositoryInterface
{
    public function findWithRelations(int|string $id, bool $lockForUpdate = false): ?LeaveRequest;

    public function hasOverlappingActiveRequest(
        int|string $employeeId,
        string $startDate,
        string $endDate,
        int|string|null $excludeId = null,
    ): bool;

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<LeaveRequest>
     */
    public function paginateForActor(User $actor, array $filters = [], int $perPage = 15): LengthAwarePaginator;
}
