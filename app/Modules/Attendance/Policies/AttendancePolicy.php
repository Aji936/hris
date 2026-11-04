<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Policies;

use App\Models\User;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\RBAC\Enums\PermissionName;

class AttendancePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(PermissionName::ATTENDANCE_VIEW->value);
    }

    public function view(User $actor, Attendance $attendance): bool
    {
        if ($actor->can(PermissionName::ATTENDANCE_VIEW->value)) {
            return true;
        }

        return $actor->can(PermissionName::ATTENDANCE_VIEW_SELF->value)
            && (int) $attendance->employee?->user_id === (int) $actor->id;
    }

    public function clock(User $actor, Employee $targetEmployee): bool
    {
        if ($actor->can(PermissionName::ATTENDANCE_MANAGE->value)) {
            return true;
        }

        return $actor->can(PermissionName::ATTENDANCE_CLOCK->value)
            && (int) $targetEmployee->user_id === (int) $actor->id;
    }
}
