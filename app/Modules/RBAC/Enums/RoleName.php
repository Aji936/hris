<?php

declare(strict_types=1);

namespace App\Modules\RBAC\Enums;

enum RoleName: string
{
    case SUPERADMIN = 'Superadmin';
    case HR_ADMIN = 'HR Admin';
    case MANAGER = 'Manager';
    case EMPLOYEE = 'Employee';

    /**
     * Baseline granular permissions assigned to each system role.
     *
     * @return list<PermissionName>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::SUPERADMIN => PermissionName::cases(),

            self::HR_ADMIN => [
                PermissionName::USER_VIEW,
                PermissionName::USER_CREATE,
                PermissionName::USER_UPDATE,
                PermissionName::AUDIT_LOG_VIEW,
                PermissionName::DEPARTMENT_VIEW,
                PermissionName::DEPARTMENT_CREATE,
                PermissionName::DEPARTMENT_UPDATE,
                PermissionName::DEPARTMENT_DELETE,
                PermissionName::POSITION_VIEW,
                PermissionName::POSITION_CREATE,
                PermissionName::POSITION_UPDATE,
                PermissionName::POSITION_DELETE,
                PermissionName::EMPLOYEE_VIEW,
                PermissionName::EMPLOYEE_VIEW_SELF,
                PermissionName::EMPLOYEE_CREATE,
                PermissionName::EMPLOYEE_UPDATE,
                PermissionName::EMPLOYEE_DELETE,
                PermissionName::ATTENDANCE_VIEW,
                PermissionName::ATTENDANCE_VIEW_SELF,
                PermissionName::ATTENDANCE_CLOCK,
                PermissionName::ATTENDANCE_MANAGE,
                PermissionName::LEAVE_VIEW,
                PermissionName::LEAVE_VIEW_SELF,
                PermissionName::LEAVE_CREATE,
                PermissionName::LEAVE_APPROVE,
                PermissionName::PAYROLL_VIEW,
                PermissionName::PAYROLL_VIEW_SELF,
                PermissionName::PAYROLL_CREATE,
            ],

            self::MANAGER => [
                PermissionName::DEPARTMENT_VIEW,
                PermissionName::POSITION_VIEW,
                PermissionName::EMPLOYEE_VIEW,
                PermissionName::EMPLOYEE_VIEW_SELF,
                PermissionName::ATTENDANCE_VIEW,
                PermissionName::ATTENDANCE_VIEW_SELF,
                PermissionName::ATTENDANCE_CLOCK,
                PermissionName::LEAVE_VIEW,
                PermissionName::LEAVE_VIEW_SELF,
                PermissionName::LEAVE_CREATE,
                PermissionName::LEAVE_APPROVE,
                PermissionName::PAYROLL_VIEW_SELF,
            ],

            self::EMPLOYEE => [
                PermissionName::EMPLOYEE_VIEW_SELF,
                PermissionName::ATTENDANCE_VIEW_SELF,
                PermissionName::ATTENDANCE_CLOCK,
                PermissionName::LEAVE_VIEW_SELF,
                PermissionName::LEAVE_CREATE,
                PermissionName::PAYROLL_VIEW_SELF,
            ],
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $role): string => $role->value,
            self::cases(),
        );
    }
}
