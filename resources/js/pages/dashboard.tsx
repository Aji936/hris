import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, CalendarDays, Clock, ReceiptText, ShieldCheck } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

export default function Dashboard() {
    const { auth } = usePage<SharedData>().props;
    const roles = auth.roles ?? [];

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
            </div>
        </AppLayout>
    );
}
