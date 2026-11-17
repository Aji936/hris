import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { AlertCircle, Building2, Briefcase, CalendarCog, CheckCircle2, Pencil, Users } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import {
    type DepartmentSummary,
    type EmployeeDirectoryPageProps,
    type EmployeeSummary,
    type EmploymentStatus,
    type LeaveTypeMasterItem,
    type PositionSummary,
} from '../../types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Employee & Master Data',
        href: '/core-hr/employees',
    },
];

type ActiveTab = 'employees' | 'departments' | 'positions' | 'leave-types';

type EditEmployeeFormData = {
    employee_code: string;
    full_name: string;
    email: string;
    department_id: string;
    position_id: string;
    manager_id: string;
    join_date: string;
    status: EmploymentStatus;
    salary_grade: string;
    bank_name: string;
    national_id_number: string;
    tax_id_number: string;
    bank_account_number: string;
};

type EditDepartmentFormData = {
    code: string;
    name: string;
    parent_id: string;
    description: string;
};

type EditPositionFormData = {
    department_id: string;
    code: string;
    title: string;
    level: string;
    base_salary_min: string;
    base_salary_max: string;
};

type EditLeaveTypeFormData = {
    code: string;
    name: string;
    default_quota_days: string;
    is_paid: boolean;
    requires_attachment: boolean;
};

export default function EmployeeDirectoryIndex({
    employees,
    departments = [],
    positions = [],
    leaveTypes = [],
    managerOptions = [],
    canManage = false,
}: EmployeeDirectoryPageProps) {
    const page = usePage<SharedData>();
    const flash = page.props.flash;

    const [activeTab, setActiveTab] = useState<ActiveTab>('employees');
    const [editingEmployee, setEditingEmployee] = useState<EmployeeSummary | null>(null);
    const [editingDepartment, setEditingDepartment] = useState<DepartmentSummary | null>(null);
    const [editingPosition, setEditingPosition] = useState<PositionSummary | null>(null);
    const [editingLeaveType, setEditingLeaveType] = useState<LeaveTypeMasterItem | null>(null);

    const employeeForm = useForm<EditEmployeeFormData>({
        employee_code: '',
        full_name: '',
        email: '',
        department_id: '',
        position_id: '',
        manager_id: 'none',
        join_date: '',
        status: 'PERMANENT',
        salary_grade: '',
        bank_name: '',
        national_id_number: '',
        tax_id_number: '',
        bank_account_number: '',
    });

    const departmentForm = useForm<EditDepartmentFormData>({
        code: '',
        name: '',
        parent_id: 'none',
        description: '',
    });

    const positionForm = useForm<EditPositionFormData>({
        department_id: '',
        code: '',
        title: '',
        level: '',
        base_salary_min: '',
        base_salary_max: '',
    });

    const leaveTypeForm = useForm<EditLeaveTypeFormData>({
        code: '',
        name: '',
        default_quota_days: '12',
        is_paid: true,
        requires_attachment: false,
    });

    const openEditEmployee = (emp: EmployeeSummary): void => {
        setEditingEmployee(emp);
        employeeForm.setData({
            employee_code: emp.employee_code,
            full_name: emp.full_name,
            email: emp.user?.email ?? '',
            department_id: emp.department ? String(emp.department.id) : '',
            position_id: emp.position ? String(emp.position.id) : '',
            manager_id: emp.manager ? String(emp.manager.id) : 'none',
            join_date: emp.join_date ?? '',
            status: emp.status,
            salary_grade: emp.salary_grade ?? '',
            bank_name: emp.bank_name ?? '',
            national_id_number: emp.national_id_number ?? '',
            tax_id_number: emp.tax_id_number ?? '',
            bank_account_number: emp.bank_account_number ?? '',
        });
        employeeForm.clearErrors();
    };

    const handleEmployeeUpdate = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        if (!editingEmployee) {
            return;
        }

        employeeForm.transform((data) => ({
            ...data,
            department_id: Number(data.department_id),
            position_id: Number(data.position_id),
            manager_id: data.manager_id === 'none' || data.manager_id === '' ? null : Number(data.manager_id),
        }));
        employeeForm.put(`/core-hr/employees/${editingEmployee.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditingEmployee(null),
        });
    };

    const openEditDepartment = (dept: DepartmentSummary): void => {
        setEditingDepartment(dept);
        departmentForm.setData({
            code: dept.code,
            name: dept.name,
            parent_id: dept.parent_id ? String(dept.parent_id) : 'none',
            description: dept.description ?? '',
        });
        departmentForm.clearErrors();
    };

    const handleDepartmentUpdate = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        if (!editingDepartment) {
            return;
        }

        departmentForm.transform((data) => ({
            ...data,
            parent_id: data.parent_id === 'none' || data.parent_id === '' ? null : Number(data.parent_id),
        }));
        departmentForm.put(`/core-hr/departments/${editingDepartment.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditingDepartment(null),
        });
    };

    const openEditPosition = (pos: PositionSummary): void => {
        setEditingPosition(pos);
        positionForm.setData({
            department_id: pos.department_id ? String(pos.department_id) : '',
            code: pos.code,
            title: pos.title,
            level: pos.level,
            base_salary_min: pos.base_salary_min ?? '',
            base_salary_max: pos.base_salary_max ?? '',
        });
        positionForm.clearErrors();
    };

    const handlePositionUpdate = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        if (!editingPosition) {
            return;
        }

        positionForm.transform((data) => ({
            ...data,
            department_id: Number(data.department_id),
            base_salary_min: data.base_salary_min === '' ? null : data.base_salary_min,
            base_salary_max: data.base_salary_max === '' ? null : data.base_salary_max,
        }));
        positionForm.put(`/core-hr/positions/${editingPosition.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditingPosition(null),
        });
    };

    const openEditLeaveType = (leaveType: LeaveTypeMasterItem): void => {
        setEditingLeaveType(leaveType);
        leaveTypeForm.setData({
            code: leaveType.code,
            name: leaveType.name,
            default_quota_days: String(leaveType.default_quota_days),
            is_paid: leaveType.is_paid,
            requires_attachment: leaveType.requires_attachment,
        });
        leaveTypeForm.clearErrors();
    };

    const handleLeaveTypeUpdate = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        if (!editingLeaveType) {
            return;
        }

        leaveTypeForm.transform((data) => ({
            ...data,
            default_quota_days: Number(data.default_quota_days),
        }));
        leaveTypeForm.put(`/attendance/leave-types/${editingLeaveType.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditingLeaveType(null),
        });
    };

    const filteredPositions = positions.filter(
        (pos) => !employeeForm.data.department_id || String(pos.department_id) === employeeForm.data.department_id,
    );

    const empErrors = employeeForm.errors as Record<string, string | undefined>;
    const deptErrors = departmentForm.errors as Record<string, string | undefined>;
    const posErrors = positionForm.errors as Record<string, string | undefined>;
    const leaveErrors = leaveTypeForm.errors as Record<string, string | undefined>;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Employee & Master Data" />

            <div className="flex flex-1 flex-col gap-6 p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Organization &amp; Employee Master Data</h1>
                        <p className="text-muted-foreground text-sm">
                            Manage employee records, organizational hierarchy, job grades, and leave policies with full audit trail.
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant={activeTab === 'employees' ? 'default' : 'outline'}
                            onClick={() => setActiveTab('employees')}
                        >
                            <Users className="size-4" />
                            Employees ({employees.data.length})
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant={activeTab === 'departments' ? 'default' : 'outline'}
                            onClick={() => setActiveTab('departments')}
                        >
                            <Building2 className="size-4" />
                            Departments ({departments.length})
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant={activeTab === 'positions' ? 'default' : 'outline'}
                            onClick={() => setActiveTab('positions')}
                        >
                            <Briefcase className="size-4" />
                            Positions ({positions.length})
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant={activeTab === 'leave-types' ? 'default' : 'outline'}
                            onClick={() => setActiveTab('leave-types')}
                        >
                            <CalendarCog className="size-4" />
                            Leave Types ({leaveTypes.length})
                        </Button>
                    </div>
                </div>

                {flash?.success && (
                    <Alert className="border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-300">
                        <CheckCircle2 className="size-4 text-emerald-600 dark:text-emerald-400" />
                        <AlertTitle>Updated</AlertTitle>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}

                {activeTab === 'employees' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Employee Directory</CardTitle>
                            <CardDescription>
                                Organization master directory and employment records.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="overflow-x-auto border-t">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 border-b text-xs uppercase">
                                        <tr>
                                            <th className="px-4 py-3">Code</th>
                                            <th className="px-4 py-3">Employee</th>
                                            <th className="px-4 py-3">Department</th>
                                            <th className="px-4 py-3">Position</th>
                                            <th className="px-4 py-3">Manager</th>
                                            <th className="px-4 py-3">Status</th>
                                            <th className="px-4 py-3">Join Date</th>
                                            {canManage && <th className="px-4 py-3 text-right">Actions</th>}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {employees.data.length === 0 ? (
                                            <tr>
                                                <td colSpan={canManage ? 8 : 7} className="text-muted-foreground px-4 py-8 text-center">
                                                    No employee records found.
                                                </td>
                                            </tr>
                                        ) : (
                                            employees.data.map((employee) => (
                                                <tr key={employee.id} className="hover:bg-muted/30 transition-colors">
                                                    <td className="px-4 py-3 font-mono text-xs">{employee.employee_code}</td>
                                                    <td className="px-4 py-3">
                                                        <p className="font-medium">{employee.full_name}</p>
                                                        {employee.user?.email && (
                                                            <p className="text-muted-foreground text-xs">{employee.user.email}</p>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3">{employee.department?.name ?? '-'}</td>
                                                    <td className="px-4 py-3">
                                                        {employee.position?.title ?? '-'}
                                                        {employee.salary_grade && (
                                                            <span className="text-muted-foreground ml-1.5 text-xs">
                                                                ({employee.salary_grade})
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 text-xs">{employee.manager?.full_name ?? '-'}</td>
                                                    <td className="px-4 py-3">
                                                        <Badge variant="outline">{employee.status}</Badge>
                                                    </td>
                                                    <td className="px-4 py-3 font-mono text-xs">{employee.join_date}</td>
                                                    {canManage && (
                                                        <td className="px-4 py-3 text-right">
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() => openEditEmployee(employee)}
                                                            >
                                                                <Pencil className="size-3.5" />
                                                                Edit
                                                            </Button>
                                                        </td>
                                                    )}
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {activeTab === 'departments' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Departments Master Data</CardTitle>
                            <CardDescription>
                                Hierarchical organizational units and reporting lines.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="overflow-x-auto border-t">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 border-b text-xs uppercase">
                                        <tr>
                                            <th className="px-4 py-3">Code</th>
                                            <th className="px-4 py-3">Department Name</th>
                                            <th className="px-4 py-3">Description</th>
                                            {canManage && <th className="px-4 py-3 text-right">Actions</th>}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {departments.map((dept) => (
                                            <tr key={dept.id} className="hover:bg-muted/30 transition-colors">
                                                <td className="px-4 py-3 font-mono text-xs font-medium">{dept.code}</td>
                                                <td className="px-4 py-3 font-medium">{dept.name}</td>
                                                <td className="text-muted-foreground px-4 py-3">{dept.description ?? '-'}</td>
                                                {canManage && (
                                                    <td className="px-4 py-3 text-right">
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => openEditDepartment(dept)}
                                                        >
                                                            <Pencil className="size-3.5" />
                                                            Edit
                                                        </Button>
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {activeTab === 'positions' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Job Positions &amp; Grades</CardTitle>
                            <CardDescription>
                                Structural job titles, grade levels, and reference base salary bands.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="overflow-x-auto border-t">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 border-b text-xs uppercase">
                                        <tr>
                                            <th className="px-4 py-3">Code</th>
                                            <th className="px-4 py-3">Position Title</th>
                                            <th className="px-4 py-3">Level / Grade</th>
                                            <th className="px-4 py-3">Salary Range (IDR)</th>
                                            {canManage && <th className="px-4 py-3 text-right">Actions</th>}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {positions.map((pos) => (
                                            <tr key={pos.id} className="hover:bg-muted/30 transition-colors">
                                                <td className="px-4 py-3 font-mono text-xs font-medium">{pos.code}</td>
                                                <td className="px-4 py-3 font-medium">{pos.title}</td>
                                                <td className="px-4 py-3">
                                                    <Badge variant="secondary">{pos.level}</Badge>
                                                </td>
                                                <td className="px-4 py-3 font-mono text-xs">
                                                    {pos.base_salary_min ?? '-'} – {pos.base_salary_max ?? '-'}
                                                </td>
                                                {canManage && (
                                                    <td className="px-4 py-3 text-right">
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => openEditPosition(pos)}
                                                        >
                                                            <Pencil className="size-3.5" />
                                                            Edit
                                                        </Button>
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {activeTab === 'leave-types' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Leave Types &amp; Default Quotas</CardTitle>
                            <CardDescription>
                                Master leave categories, default annual quota days, and attachment policies.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="overflow-x-auto border-t">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 border-b text-xs uppercase">
                                        <tr>
                                            <th className="px-4 py-3">Code</th>
                                            <th className="px-4 py-3">Leave Name</th>
                                            <th className="px-4 py-3">Default Quota</th>
                                            <th className="px-4 py-3">Paid Status</th>
                                            <th className="px-4 py-3">Attachment Policy</th>
                                            {canManage && <th className="px-4 py-3 text-right">Actions</th>}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {leaveTypes.map((lt) => (
                                            <tr key={lt.id} className="hover:bg-muted/30 transition-colors">
                                                <td className="px-4 py-3 font-mono text-xs font-medium">{lt.code}</td>
                                                <td className="px-4 py-3 font-medium">{lt.name}</td>
                                                <td className="px-4 py-3">{lt.default_quota_days} days</td>
                                                <td className="px-4 py-3">
                                                    <Badge variant={lt.is_paid ? 'default' : 'secondary'}>
                                                        {lt.is_paid ? 'Paid' : 'Unpaid'}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-3 text-xs">
                                                    {lt.requires_attachment ? 'Required' : 'Optional'}
                                                </td>
                                                {canManage && (
                                                    <td className="px-4 py-3 text-right">
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => openEditLeaveType(lt)}
                                                        >
                                                            <Pencil className="size-3.5" />
                                                            Edit
                                                        </Button>
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* Edit Employee Modal */}
            <Dialog open={editingEmployee !== null} onOpenChange={(open) => !open && setEditingEmployee(null)}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <form onSubmit={handleEmployeeUpdate} className="flex flex-col gap-4">
                        <DialogHeader>
                            <DialogTitle>Edit Employee — {editingEmployee?.full_name}</DialogTitle>
                            <DialogDescription>
                                Update organization placement, profile details, and encrypted PII. All changes are recorded in Audit Log.
                            </DialogDescription>
                        </DialogHeader>

                        {empErrors.domain && (
                            <Alert variant="destructive">
                                <AlertCircle className="size-4" />
                                <AlertTitle>Validation Error</AlertTitle>
                                <AlertDescription>{empErrors.domain}</AlertDescription>
                            </Alert>
                        )}

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="emp_code">Employee Code</Label>
                                <Input
                                    id="emp_code"
                                    value={employeeForm.data.employee_code}
                                    onChange={(e) => employeeForm.setData('employee_code', e.target.value)}
                                    required
                                />
                                {empErrors.employee_code && <p className="text-destructive text-xs">{empErrors.employee_code}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="emp_full_name">Full Name</Label>
                                <Input
                                    id="emp_full_name"
                                    value={employeeForm.data.full_name}
                                    onChange={(e) => employeeForm.setData('full_name', e.target.value)}
                                    required
                                />
                                {empErrors.full_name && <p className="text-destructive text-xs">{empErrors.full_name}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="emp_email">Account Email</Label>
                                <Input
                                    id="emp_email"
                                    type="email"
                                    value={employeeForm.data.email}
                                    onChange={(e) => employeeForm.setData('email', e.target.value)}
                                    required
                                />
                                {empErrors.email && <p className="text-destructive text-xs">{empErrors.email}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="emp_status">Employment Status</Label>
                                <Select
                                    value={employeeForm.data.status}
                                    onValueChange={(value) => employeeForm.setData('status', value as EmploymentStatus)}
                                >
                                    <SelectTrigger id="emp_status">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="PROBATION">PROBATION</SelectItem>
                                        <SelectItem value="PERMANENT">PERMANENT</SelectItem>
                                        <SelectItem value="CONTRACT">CONTRACT</SelectItem>
                                        <SelectItem value="TERMINATED">TERMINATED</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="emp_department">Department</Label>
                                <Select
                                    value={employeeForm.data.department_id}
                                    onValueChange={(value) => {
                                        employeeForm.setData('department_id', value);
                                        const firstDeptPos = positions.find((p) => String(p.department_id) === value);
                                        if (firstDeptPos) {
                                            employeeForm.setData('position_id', String(firstDeptPos.id));
                                        }
                                    }}
                                >
                                    <SelectTrigger id="emp_department">
                                        <SelectValue placeholder="Select department" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {departments.map((dept) => (
                                            <SelectItem key={dept.id} value={String(dept.id)}>
                                                {dept.name} ({dept.code})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {empErrors.department_id && <p className="text-destructive text-xs">{empErrors.department_id}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="emp_position">Position / Title</Label>
                                <Select
                                    value={employeeForm.data.position_id}
                                    onValueChange={(value) => employeeForm.setData('position_id', value)}
                                >
                                    <SelectTrigger id="emp_position">
                                        <SelectValue placeholder="Select position" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {filteredPositions.map((pos) => (
                                            <SelectItem key={pos.id} value={String(pos.id)}>
                                                {pos.title} ({pos.level})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {empErrors.position_id && <p className="text-destructive text-xs">{empErrors.position_id}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="emp_manager">Direct Manager</Label>
                                <Select
                                    value={employeeForm.data.manager_id}
                                    onValueChange={(value) => employeeForm.setData('manager_id', value)}
                                >
                                    <SelectTrigger id="emp_manager">
                                        <SelectValue placeholder="None (Top Level)" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">No Direct Manager</SelectItem>
                                        {managerOptions
                                            .filter((mgr) => mgr.id !== editingEmployee?.id)
                                            .map((mgr) => (
                                                <SelectItem key={mgr.id} value={String(mgr.id)}>
                                                    {mgr.full_name} ({mgr.employee_code})
                                                </SelectItem>
                                            ))}
                                    </SelectContent>
                                </Select>
                                {empErrors.manager_id && <p className="text-destructive text-xs">{empErrors.manager_id}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="emp_join_date">Join Date</Label>
                                <Input
                                    id="emp_join_date"
                                    type="date"
                                    value={employeeForm.data.join_date}
                                    onChange={(e) => employeeForm.setData('join_date', e.target.value)}
                                    required
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="emp_grade">Salary Grade</Label>
                                <Input
                                    id="emp_grade"
                                    value={employeeForm.data.salary_grade}
                                    onChange={(e) => employeeForm.setData('salary_grade', e.target.value)}
                                    placeholder="e.g. S2, S3, M1"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="emp_bank_name">Bank Name</Label>
                                <Input
                                    id="emp_bank_name"
                                    value={employeeForm.data.bank_name}
                                    onChange={(e) => employeeForm.setData('bank_name', e.target.value)}
                                    placeholder="Leave blank to keep existing"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="emp_nik">NIK (Encrypted)</Label>
                                <Input
                                    id="emp_nik"
                                    value={employeeForm.data.national_id_number}
                                    onChange={(e) => employeeForm.setData('national_id_number', e.target.value)}
                                    placeholder="Leave blank to keep existing"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="emp_npwp">NPWP (Encrypted)</Label>
                                <Input
                                    id="emp_npwp"
                                    value={employeeForm.data.tax_id_number}
                                    onChange={(e) => employeeForm.setData('tax_id_number', e.target.value)}
                                    placeholder="Leave blank to keep existing"
                                />
                            </div>

                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="emp_bank_acc">Bank Account Number (Encrypted)</Label>
                                <Input
                                    id="emp_bank_acc"
                                    value={employeeForm.data.bank_account_number}
                                    onChange={(e) => employeeForm.setData('bank_account_number', e.target.value)}
                                    placeholder="Leave blank to keep existing encrypted account number"
                                />
                            </div>
                        </div>

                        <DialogFooter className="pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setEditingEmployee(null)}
                                disabled={employeeForm.processing}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={employeeForm.processing}>
                                {employeeForm.processing ? 'Saving...' : 'Save Changes'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Department Modal */}
            <Dialog open={editingDepartment !== null} onOpenChange={(open) => !open && setEditingDepartment(null)}>
                <DialogContent className="sm:max-w-md">
                    <form onSubmit={handleDepartmentUpdate} className="flex flex-col gap-4">
                        <DialogHeader>
                            <DialogTitle>Edit Department — {editingDepartment?.name}</DialogTitle>
                            <DialogDescription>
                                Update department code, display name, or parent hierarchy.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="dept_code">Department Code</Label>
                                <Input
                                    id="dept_code"
                                    value={departmentForm.data.code}
                                    onChange={(e) => departmentForm.setData('code', e.target.value)}
                                    required
                                />
                                {deptErrors.code && <p className="text-destructive text-xs">{deptErrors.code}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="dept_name">Department Name</Label>
                                <Input
                                    id="dept_name"
                                    value={departmentForm.data.name}
                                    onChange={(e) => departmentForm.setData('name', e.target.value)}
                                    required
                                />
                                {deptErrors.name && <p className="text-destructive text-xs">{deptErrors.name}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="dept_desc">Description</Label>
                                <Input
                                    id="dept_desc"
                                    value={departmentForm.data.description}
                                    onChange={(e) => departmentForm.setData('description', e.target.value)}
                                />
                            </div>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setEditingDepartment(null)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={departmentForm.processing}>
                                {departmentForm.processing ? 'Saving...' : 'Update Department'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Position Modal */}
            <Dialog open={editingPosition !== null} onOpenChange={(open) => !open && setEditingPosition(null)}>
                <DialogContent className="sm:max-w-md">
                    <form onSubmit={handlePositionUpdate} className="flex flex-col gap-4">
                        <DialogHeader>
                            <DialogTitle>Edit Position — {editingPosition?.title}</DialogTitle>
                            <DialogDescription>
                                Update job code, title, level grade, and reference salary range.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="pos_code">Position Code</Label>
                                <Input
                                    id="pos_code"
                                    value={positionForm.data.code}
                                    onChange={(e) => positionForm.setData('code', e.target.value)}
                                    required
                                />
                                {posErrors.code && <p className="text-destructive text-xs">{posErrors.code}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="pos_title">Position Title</Label>
                                <Input
                                    id="pos_title"
                                    value={positionForm.data.title}
                                    onChange={(e) => positionForm.setData('title', e.target.value)}
                                    required
                                />
                                {posErrors.title && <p className="text-destructive text-xs">{posErrors.title}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="pos_level">Grade Level</Label>
                                <Input
                                    id="pos_level"
                                    value={positionForm.data.level}
                                    onChange={(e) => positionForm.setData('level', e.target.value)}
                                    required
                                />
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="pos_min">Min Base Salary</Label>
                                    <Input
                                        id="pos_min"
                                        type="number"
                                        step="0.01"
                                        value={positionForm.data.base_salary_min}
                                        onChange={(e) => positionForm.setData('base_salary_min', e.target.value)}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="pos_max">Max Base Salary</Label>
                                    <Input
                                        id="pos_max"
                                        type="number"
                                        step="0.01"
                                        value={positionForm.data.base_salary_max}
                                        onChange={(e) => positionForm.setData('base_salary_max', e.target.value)}
                                    />
                                </div>
                            </div>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setEditingPosition(null)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={positionForm.processing}>
                                {positionForm.processing ? 'Saving...' : 'Update Position'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Leave Type Modal */}
            <Dialog open={editingLeaveType !== null} onOpenChange={(open) => !open && setEditingLeaveType(null)}>
                <DialogContent className="sm:max-w-md">
                    <form onSubmit={handleLeaveTypeUpdate} className="flex flex-col gap-4">
                        <DialogHeader>
                            <DialogTitle>Edit Leave Type — {editingLeaveType?.name}</DialogTitle>
                            <DialogDescription>
                                Update leave code, display name, and default annual quota days.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="lt_code">Leave Code</Label>
                                <Input
                                    id="lt_code"
                                    value={leaveTypeForm.data.code}
                                    onChange={(e) => leaveTypeForm.setData('code', e.target.value)}
                                    required
                                />
                                {leaveErrors.code && <p className="text-destructive text-xs">{leaveErrors.code}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="lt_name">Leave Name</Label>
                                <Input
                                    id="lt_name"
                                    value={leaveTypeForm.data.name}
                                    onChange={(e) => leaveTypeForm.setData('name', e.target.value)}
                                    required
                                />
                                {leaveErrors.name && <p className="text-destructive text-xs">{leaveErrors.name}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="lt_quota">Default Annual Quota (Days)</Label>
                                <Input
                                    id="lt_quota"
                                    type="number"
                                    min={0}
                                    max={365}
                                    value={leaveTypeForm.data.default_quota_days}
                                    onChange={(e) => leaveTypeForm.setData('default_quota_days', e.target.value)}
                                    required
                                />
                                {leaveErrors.default_quota_days && (
                                    <p className="text-destructive text-xs">{leaveErrors.default_quota_days}</p>
                                )}
                            </div>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setEditingLeaveType(null)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={leaveTypeForm.processing}>
                                {leaveTypeForm.processing ? 'Saving...' : 'Update Leave Type'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
