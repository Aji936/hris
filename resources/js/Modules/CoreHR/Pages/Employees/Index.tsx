import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { type EmployeeDirectoryPageProps } from '../../types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Employee Directory',
        href: '/core-hr/employees',
    },
];

export default function EmployeeDirectoryIndex({ employees }: EmployeeDirectoryPageProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Employee Directory" />
            <div className="flex flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Employee Directory</h1>
                    <p className="text-muted-foreground text-sm">
                        Organization master directory and employment records.
                    </p>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted/50 border-b text-xs uppercase">
                            <tr>
                                <th className="px-4 py-3">Code</th>
                                <th className="px-4 py-3">Employee</th>
                                <th className="px-4 py-3">Department</th>
                                <th className="px-4 py-3">Position</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">Join Date</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {employees.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="text-muted-foreground px-4 py-8 text-center">
                                        No employee records found.
                                    </td>
                                </tr>
                            ) : (
                                employees.data.map((employee) => (
                                    <tr key={employee.id}>
                                        <td className="px-4 py-3 font-mono text-xs">{employee.employee_code}</td>
                                        <td className="px-4 py-3 font-medium">{employee.full_name}</td>
                                        <td className="px-4 py-3">{employee.department?.name ?? '-'}</td>
                                        <td className="px-4 py-3">{employee.position?.title ?? '-'}</td>
                                        <td className="px-4 py-3">{employee.status}</td>
                                        <td className="px-4 py-3">{employee.join_date}</td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}

