<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Policies;

use App\Models\User;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\RBAC\Enums\PermissionName;

class PayslipPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(PermissionName::PAYROLL_VIEW->value)
            || $actor->can(PermissionName::PAYROLL_VIEW_SELF->value);
    }

    public function view(User $actor, Payslip $payslip): bool
    {
        if ($actor->can(PermissionName::PAYROLL_VIEW->value)) {
            return true;
        }

        if (! $actor->can(PermissionName::PAYROLL_VIEW_SELF->value)) {
            return false;
        }

        $employee = $payslip->employee;
        if ($employee === null || $employee->user_id === null) {
            return false;
        }

        return (int) $employee->user_id === (int) $actor->id
            && $payslip->status->isEmployeeVisible();
    }

    public function download(User $actor, Payslip $payslip): bool
    {
        return $this->view($actor, $payslip);
    }
}
