import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import {
    type BreadcrumbItem,
    type PayslipListProps,
    type PayslipRecord,
    type PayslipStatusValue,
} from '@/types';
import { Head } from '@inertiajs/react';
import { AlertCircle, Download, Eye, FileCheck2, Wallet } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'My Payslips',
        href: '/payroll/my-payslips',
    },
];

function formatRupiah(decimalAmount: string): string {
    const parsed = Number.parseFloat(decimalAmount);
    if (Number.isNaN(parsed)) {
        return `Rp ${decimalAmount}`;
    }

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(parsed);
}

function getPayslipStatusBadgeClass(status: PayslipStatusValue): string {
    switch (status) {
        case 'PAID':
            return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400';
        case 'PUBLISHED':
            return 'border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-400';
        case 'DRAFT':
            return 'border-slate-500/30 bg-slate-500/10 text-slate-700 dark:text-slate-400';
    }
}

export default function MyPayslips({
    hasEmployeeProfile,
    employee,
    summary,
    payslips,
}: PayslipListProps) {
    const [selectedPayslip, setSelectedPayslip] = useState<PayslipRecord | null>(null);

    const allowanceItems = (selectedPayslip?.items ?? []).filter((item) => item.type === 'ALLOWANCE');
    const deductionItems = (selectedPayslip?.items ?? []).filter((item) => item.type === 'DEDUCTION');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My Payslips" />

            <div className="flex flex-1 flex-col gap-6 p-6">
                <div className="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">My Payslips</h1>
                        <p className="text-muted-foreground text-sm">
                            Access your official published monthly earnings statements and download PDF payslips.
                        </p>
                    </div>
                    {employee && (
                        <div className="text-sm sm:text-right">
                            <p className="font-medium">{employee.full_name}</p>
                            <p className="text-muted-foreground text-xs">
                                {employee.employee_code}
                                {employee.department_name ? ` • ${employee.department_name}` : ''}
                            </p>
                        </div>
                    )}
                </div>

                {!hasEmployeeProfile && (
                    <Alert variant="destructive">
                        <AlertCircle className="size-4" />
                        <AlertTitle>No Linked Employee Profile</AlertTitle>
                        <AlertDescription>
                            Your user account is not linked to an employee profile yet. Please contact HR Administration.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription className="flex items-center gap-1.5">
                                <Wallet className="size-4" />
                                Latest Take Home Pay
                            </CardDescription>
                            <CardTitle className="text-2xl font-bold text-emerald-600 tabular-nums dark:text-emerald-400">
                                {formatRupiah(summary.latest_net_salary)}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-muted-foreground text-xs">
                                {summary.latest_period_title ? `Period: ${summary.latest_period_title}` : 'No published period yet'}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription className="flex items-center gap-1.5">
                                <FileCheck2 className="size-4" />
                                Published Statements
                            </CardDescription>
                            <CardTitle className="text-2xl font-bold tabular-nums">
                                {summary.published_count} payslip(s)
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-muted-foreground text-xs">Official statements available for PDF download</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Cumulative Net Disbursed</CardDescription>
                            <CardTitle className="text-2xl font-bold tabular-nums">
                                {formatRupiah(summary.total_net_received)}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-muted-foreground text-xs">Total net pay across listed statements</p>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Official Payslip Statements</CardTitle>
                        <CardDescription>
                            Only finalized payslips with PUBLISHED or PAID status appear in your self-service portal.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto border-t">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 border-b text-xs uppercase">
                                    <tr>
                                        <th className="px-4 py-3">Payslip No.</th>
                                        <th className="px-4 py-3">Payroll Period</th>
                                        <th className="px-4 py-3">Basic Salary</th>
                                        <th className="px-4 py-3">Allowances</th>
                                        <th className="px-4 py-3">Deductions</th>
                                        <th className="px-4 py-3">Net Salary (THP)</th>
                                        <th className="px-4 py-3">Status</th>
                                        <th className="px-4 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {payslips.length === 0 ? (
                                        <tr>
                                            <td colSpan={8} className="text-muted-foreground px-4 py-8 text-center">
                                                No published payslips available yet.
                                            </td>
                                        </tr>
                                    ) : (
                                        payslips.map((payslip) => (
                                            <tr key={payslip.id} className="hover:bg-muted/30 transition-colors">
                                                <td className="px-4 py-3 font-mono text-xs font-medium">
                                                    {payslip.payslip_number}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <p className="font-medium">
                                                        {payslip.payroll?.title ?? `Batch #${payslip.payroll_id}`}
                                                    </p>
                                                    {payslip.payroll?.payroll_period && (
                                                        <p className="text-muted-foreground text-xs">
                                                            {payslip.payroll.payroll_period}
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 tabular-nums">{formatRupiah(payslip.basic_salary)}</td>
                                                <td className="px-4 py-3 text-emerald-600 tabular-nums dark:text-emerald-400">
                                                    +{formatRupiah(payslip.total_allowance)}
                                                </td>
                                                <td className="px-4 py-3 text-rose-600 tabular-nums dark:text-rose-400">
                                                    -{formatRupiah(payslip.total_deduction)}
                                                </td>
                                                <td className="px-4 py-3 font-semibold tabular-nums">
                                                    {formatRupiah(payslip.net_salary)}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Badge variant="outline" className={getPayslipStatusBadgeClass(payslip.status)}>
                                                        {payslip.status}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <div className="flex items-center justify-end gap-2">
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => setSelectedPayslip(payslip)}
                                                        >
                                                            <Eye className="size-4" />
                                                            Details
                                                        </Button>
                                                        <Button asChild variant="outline" size="sm">
                                                            <a href={`/payroll/payslips/${payslip.id}/pdf`}>
                                                                <Download className="size-4" />
                                                                Download PDF
                                                            </a>
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Dialog open={selectedPayslip !== null} onOpenChange={(open) => !open && setSelectedPayslip(null)}>
                <DialogContent className="sm:max-w-xl">
                    {selectedPayslip && (
                        <div className="flex flex-col gap-4">
                            <DialogHeader>
                                <DialogTitle>
                                    {selectedPayslip.payroll?.title ?? selectedPayslip.payslip_number}
                                </DialogTitle>
                                <DialogDescription>
                                    Payslip No. {selectedPayslip.payslip_number} •{' '}
                                    {selectedPayslip.employee_snapshot.full_name ?? employee?.full_name}
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="rounded-lg border p-3">
                                    <p className="mb-2 text-xs font-semibold uppercase text-emerald-600 dark:text-emerald-400">
                                        Earnings &amp; Allowances
                                    </p>
                                    <div className="space-y-1.5 text-sm">
                                        <div className="flex justify-between">
                                            <span>Basic Salary</span>
                                            <span className="font-medium tabular-nums">
                                                {formatRupiah(selectedPayslip.basic_salary)}
                                            </span>
                                        </div>
                                        {allowanceItems.map((item) => (
                                            <div key={item.id} className="flex justify-between text-xs">
                                                <span className="text-muted-foreground">{item.name}</span>
                                                <span className="tabular-nums">{formatRupiah(item.amount)}</span>
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                <div className="rounded-lg border p-3">
                                    <p className="mb-2 text-xs font-semibold uppercase text-rose-600 dark:text-rose-400">
                                        Deductions
                                    </p>
                                    <div className="space-y-1.5 text-sm">
                                        {deductionItems.length === 0 ? (
                                            <p className="text-muted-foreground text-xs">No deductions recorded.</p>
                                        ) : (
                                            deductionItems.map((item) => (
                                                <div key={item.id} className="flex justify-between text-xs">
                                                    <span className="text-muted-foreground">{item.name}</span>
                                                    <span className="tabular-nums">{formatRupiah(item.amount)}</span>
                                                </div>
                                            ))
                                        )}
                                    </div>
                                </div>
                            </div>

                            <div className="bg-muted/40 flex items-center justify-between rounded-lg border p-4">
                                <div>
                                    <p className="text-muted-foreground text-xs uppercase">Take Home Pay (Net Salary)</p>
                                    <p className="text-xl font-bold text-emerald-600 tabular-nums dark:text-emerald-400">
                                        {formatRupiah(selectedPayslip.net_salary)}
                                    </p>
                                </div>
                                <Button asChild>
                                    <a href={`/payroll/payslips/${selectedPayslip.id}/pdf`}>
                                        <Download className="size-4" />
                                        Download Official PDF
                                    </a>
                                </Button>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

