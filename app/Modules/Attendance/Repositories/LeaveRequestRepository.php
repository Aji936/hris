<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Repositories;

use App\Models\User;
use App\Modules\Attendance\Contracts\LeaveRequestRepositoryInterface;
use App\Modules\Attendance\Enums\LeaveRequestStatus;
use App\Modules\Attendance\Models\LeaveRequest;
use App\Modules\RBAC\Enums\PermissionName;
use App\Modules\RBAC\Enums\RoleName;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<LeaveRequest>
 */
class LeaveRequestRepository extends BaseRepository implements LeaveRequestRepositoryInterface
{
    protected function model(): string
    {
        return LeaveRequest::class;
    }

    public function findWithRelations(int|string $id, bool $lockForUpdate = false): ?LeaveRequest
    {
        $query = $this->query()
            ->with([
                'employee.manager',
                'leaveType',
                'approver:id,name,email',
            ])
            ->whereKey($id);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        /** @var LeaveRequest|null $leaveRequest */
        $leaveRequest = $query->first();

        return $leaveRequest;
    }

    public function hasOverlappingActiveRequest(
        int|string $employeeId,
        string $startDate,
        string $endDate,
        int|string|null $excludeId = null,
    ): bool {
        return $this->query()
            ->where('employee_id', $employeeId)
            ->whereIn('status', [
                LeaveRequestStatus::PENDING->value,
                LeaveRequestStatus::APPROVED->value,
            ])
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->when(
                $excludeId !== null,
                static fn (Builder $query) => $query->whereKeyNot($excludeId),
            )
            ->exists();
    }

    public function paginateForActor(User $actor, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $canViewAll = $actor->hasRole([RoleName::SUPERADMIN->value, RoleName::HR_ADMIN->value])
            && $actor->can(PermissionName::LEAVE_VIEW->value);
        $canViewTeam = $actor->can(PermissionName::LEAVE_VIEW->value);

        return $this->query()
            ->with([
                'employee:id,user_id,manager_id,employee_code,full_name,department_id',
                'employee.manager:id,user_id,employee_code,full_name',
                'leaveType:id,code,name,is_paid,requires_attachment',
                'approver:id,name,email',
            ])
            ->when(! $canViewAll, static function (Builder $query) use ($actor, $canViewTeam): void {
                $query->whereHas('employee', static function (Builder $employeeQuery) use ($actor, $canViewTeam): void {
                    $employeeQuery->where(static function (Builder $scopeQuery) use ($actor, $canViewTeam): void {
                        $scopeQuery->where('user_id', $actor->id);

                        if ($canViewTeam) {
                            $scopeQuery->orWhereHas(
                                'manager',
                                static fn (Builder $managerQuery) => $managerQuery->where('user_id', $actor->id),
                            );
                        }
                    });
                });
            })
            ->when(
                ! empty($filters['employee_id']),
                static fn (Builder $query) => $query->where('employee_id', $filters['employee_id']),
            )
            ->when(
                ! empty($filters['status']),
                static fn (Builder $query) => $query->where('status', $filters['status']),
            )
            ->when(
                ! empty($filters['leave_type_id']),
                static fn (Builder $query) => $query->where('leave_type_id', $filters['leave_type_id']),
            )
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
