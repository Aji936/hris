<?php

declare(strict_types=1);

namespace App\Modules\RBAC\Enums;

enum PermissionName: string
{
    // RBAC & Audit
    case USER_VIEW = 'user.view';
    case USER_CREATE = 'user.create';
    case USER_UPDATE = 'user.update';
    case USER_DELETE = 'user.delete';
    case ROLE_VIEW = 'role.view';
    case ROLE_MANAGE = 'role.manage';
    case AUDIT_LOG_VIEW = 'audit_log.view';

    // Core HR: Organization & Employees
    case DEPARTMENT_VIEW = 'department.view';
    case DEPARTMENT_CREATE = 'department.create';
    case DEPARTMENT_UPDATE = 'department.update';
    case DEPARTMENT_DELETE = 'department.delete';

    case POSITION_VIEW = 'position.view';
    case POSITION_CREATE = 'position.create';
    case POSITION_UPDATE = 'position.update';
    case POSITION_DELETE = 'position.delete';

    case EMPLOYEE_VIEW = 'employee.view';
    case EMPLOYEE_VIEW_SELF = 'employee.view_self';
    case EMPLOYEE_CREATE = 'employee.create';
    case EMPLOYEE_UPDATE = 'employee.update';
    case EMPLOYEE_DELETE = 'employee.delete';

    // Attendance & Leave Management
    case ATTENDANCE_VIEW = 'attendance.view';
    case ATTENDANCE_VIEW_SELF = 'attendance.view_self';
    case ATTENDANCE_CLOCK = 'attendance.clock';
    case ATTENDANCE_MANAGE = 'attendance.manage';

    case LEAVE_VIEW = 'leave.view';
    case LEAVE_VIEW_SELF = 'leave.view_self';
    case LEAVE_CREATE = 'leave.create';
    case LEAVE_APPROVE = 'leave.approve';

    // Payroll
    case PAYROLL_VIEW = 'payroll.view';
    case PAYROLL_VIEW_SELF = 'payroll.view_self';
    case PAYROLL_CREATE = 'payroll.create';
    case PAYROLL_APPROVE = 'payroll.approve';
    case PAYROLL_PUBLISH = 'payroll.publish';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $permission): string => $permission->value,
            self::cases(),
        );
    }
}
