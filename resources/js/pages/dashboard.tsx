import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { AlertTriangle, ArrowRight, CalendarDays, Clock, ReceiptText, ShieldCheck, Users } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

export interface ApproachingRetirementItem {
    id: number;
    employee_code: string;
    full_name: string;
    department_name: string | null;
    position_title: string | null;
    date_of_birth: string | null;
    retirement_date: string | null;
    age: number | null;
    days_until_retirement: number | null;
}

interface DashboardPageProps {
    approachingRetirements?: ApproachingRetirementItem[];
}

export default function Dashboard({ approachingRetirements = [] }: DashboardPageProps) {
    const { auth } = usePage<SharedData>().props;
    const roles = auth.roles ?? [];
    const permissions = auth.permissions ?? [];
    const canViewDirectory = permissions.includes('employee.view');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Welcome back, {auth.user.name}</h1>
                        <p className="text-muted-foreground text-sm">
                            Employee Self-Service (ESS) portal for daily attendance, leave balances, and official payslips.
                        </p>
                    </div>
                    {roles.length > 0 && (
                        <div className="flex flex-wrap items-center gap-2">
                            {roles.map((role) => (
                                <Badge key={role} variant="secondary" className="gap-1.5">
                                    <ShieldCheck className="size-3.5" />
                                    {role}
                                </Badge>
                            ))}
                        </div>
                    )}
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <Card className="justify-between">
                        <CardHeader>
                            <div className="bg-primary/10 text-primary mb-2 flex size-10 items-center justify-center rounded-lg">
                                <Clock className="size-5" />
                            </div>
                            <CardTitle>Daily Attendance</CardTitle>
                            <CardDescription>
                                Record real-time clock-in &amp; clock-out with geolocation and review your monthly attendance log.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button asChild variant="outline" className="w-full justify-between">
                                <Link href="/attendance">
                                    Open Attendance
                                    <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>

                    <Card className="justify-between">
                        <CardHeader>
                            <div className="bg-primary/10 text-primary mb-2 flex size-10 items-center justify-center rounded-lg">
                                <CalendarDays className="size-5" />
                            </div>
                            <CardTitle>Leave Requests</CardTitle>
                            <CardDescription>
                                Check remaining annual quota, submit new leave applications with attachments, and track approvals.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button asChild variant="outline" className="w-full justify-between">
                                <Link href="/attendance/leave-requests">
                                    Manage Leaves
                                    <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>

                    <Card className="justify-between">
                        <CardHeader>
                            <div className="bg-primary/10 text-primary mb-2 flex size-10 items-center justify-center rounded-lg">
                                <ReceiptText className="size-5" />
                            </div>
                            <CardTitle>My Payslips</CardTitle>
                            <CardDescription>
                                View published monthly take-home pay summaries and download official PDF payslip statements.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button asChild variant="outline" className="w-full justify-between">
                                <Link href="/payroll/my-payslips">
                                    View Payslips
                                    <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                {canViewDirectory && (
                    <Card>
                        <CardHeader className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <AlertTriangle className="size-5 text-amber-500" />
                                    Karyawan Mendekati Masa Pensiun (6 Bulan Ke Depan)
                                </CardTitle>
                                <CardDescription>
                                    Monitoring otomatis karyawan aktif yang mendekati usia pensiun 55 tahun dalam 180 hari ke depan.
                                </CardDescription>
                            </div>
                            <Button asChild variant="outline" size="sm">
                                <Link href="/core-hr/employees">
                                    <Users className="size-4" />
                                    Buka Direktori Karyawan
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="overflow-x-auto border-t">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 border-b text-xs uppercase">
                                        <tr>
                                            <th className="px-4 py-3">Kode</th>
                                            <th className="px-4 py-3">Nama Karyawan</th>
                                            <th className="px-4 py-3">Departemen &amp; Jabatan</th>
                                            <th className="px-4 py-3">Tanggal Lahir / Usia</th>
                                            <th className="px-4 py-3">Tanggal Pensiun</th>
                                            <th className="px-4 py-3">Status Peringatan</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {approachingRetirements.length === 0 ? (
                                            <tr>
                                                <td colSpan={6} className="text-muted-foreground px-4 py-6 text-center">
                                                    Tidak ada karyawan yang memasuki masa pensiun dalam 6 bulan ke depan.
                                                </td>
                                            </tr>
                                        ) : (
                                            approachingRetirements.map((item) => (
                                                <tr key={item.id} className="hover:bg-muted/30 transition-colors">
                                                    <td className="px-4 py-3 font-mono text-xs">{item.employee_code}</td>
                                                    <td className="px-4 py-3 font-medium">{item.full_name}</td>
                                                    <td className="px-4 py-3 text-xs">
                                                        {item.department_name ?? '-'} • {item.position_title ?? '-'}
                                                    </td>
                                                    <td className="px-4 py-3 font-mono text-xs">
                                                        {item.date_of_birth ?? '-'} ({item.age ?? '-'} thn)
                                                    </td>
                                                    <td className="px-4 py-3 font-mono text-xs font-semibold">
                                                        {item.retirement_date ?? '-'}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <Badge
                                                            variant="outline"
                                                            className="border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300"
                                                        >
                                                            Mendekati Pensiun ({item.days_until_retirement ?? 0} hari lagi)
                                                        </Badge>
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
