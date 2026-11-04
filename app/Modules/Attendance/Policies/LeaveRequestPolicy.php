<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Policies;

use App\Models\User;
use App\Modules\Attendance\Models\LeaveRequest;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\RBAC\Enums\PermissionName;
use App\Modules\RBAC\Enums\RoleName;

class LeaveRequestPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(PermissionName::LEAVE_VIEW->value)
            || $actor->can(PermissionName::LEAVE_VIEW_SELF->value);
    }

    public function view(User $actor, LeaveRequest $leaveRequest): bool
    {
        $employee = $leaveRequest->employee;

        if (
            $actor->can(PermissionName::LEAVE_VIEW_SELF->value)
            && $employee !== null
            && $employee->user_id !== null
            && (int) $employee->user_id === (int) $actor->id
        ) {
            return true;
        }

        if (! $actor->can(PermissionName::LEAVE_VIEW->value)) {
            return false;
        }

        if ($actor->hasRole(RoleName::HR_ADMIN->value)) {
            return true;
        }

        return $employee?->manager !== null
            && $employee->manager->user_id !== null
            && (int) $employee->manager->user_id === (int) $actor->id;
    }

    public function create(User $actor, Employee $targetEmployee): bool
    {
        if (! $actor->can(PermissionName::LEAVE_CREATE->value)) {
            return false;
        }

        if ($actor->hasRole(RoleName::HR_ADMIN->value)) {
            return true;
        }

        return $targetEmployee->user_id !== null
            && (int) $targetEmployee->user_id === (int) $actor->id;
    }

    public function approve(User $actor, LeaveRequest $leaveRequest): bool
    {
        return $this->canAction($actor, $leaveRequest);
    }

    public function reject(User $actor, LeaveRequest $leaveRequest): bool
    {
        return $this->canAction($actor, $leaveRequest);
    }

    private function canAction(User $actor, LeaveRequest $leaveRequest): bool
    {
        $employee = $leaveRequest->employee;
        if ($employee === null) {
            return false;
        }

        if ($employee->user_id !== null && (int) $employee->user_id === (int) $actor->id) {
            return false;
        }

        if (! $actor->can(PermissionName::LEAVE_APPROVE->value)) {
            return false;
        }

        if ($actor->hasRole(RoleName::HR_ADMIN->value)) {
            return true;
        }

        return $employee->manager !== null
            && $employee->manager->user_id !== null
            && (int) $employee->manager->user_id === (int) $actor->id;
    }
}
