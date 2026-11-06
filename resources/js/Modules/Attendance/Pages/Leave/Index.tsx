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
    DialogTrigger,
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
import {
    type BreadcrumbItem,
    type LeaveBalanceProps,
    type LeaveRequestStatusValue,
    type SharedData,
} from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { AlertCircle, CalendarPlus, CheckCircle2, Paperclip } from 'lucide-react';
import { type FormEvent, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Leave Requests',
        href: '/attendance/leave-requests',
    },
];

type LeaveFormState = {
    leave_type_id: string;
    start_date: string;
    end_date: string;
    reason: string;
    attachment: File | null;
};

function getLeaveStatusBadgeClass(status: LeaveRequestStatusValue): string {
    switch (status) {
        case 'APPROVED':
            return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400';
        case 'PENDING':
            return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-400';
        case 'REJECTED':
            return 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-400';
        case 'DRAFT':
        case 'CANCELLED':
            return 'border-slate-500/30 bg-slate-500/10 text-slate-700 dark:text-slate-400';
    }
}

function formatDateRange(startDate: string | null, endDate: string | null): string {
    if (!startDate) {
        return '-';
    }

    if (!endDate || startDate === endDate) {
        return startDate;
    }

    return `${startDate} → ${endDate}`;
}

export default function LeaveIndex({
    currentYear,
    hasEmployeeProfile,
    leaveBalances,
    leaveTypes,
    leaveRequests,
}: LeaveBalanceProps) {
    const page = usePage<SharedData>();
    const flash = page.props.flash;

    const [isDialogOpen, setIsDialogOpen] = useState<boolean>(false);

    const form = useForm<LeaveFormState>({
        leave_type_id: leaveTypes[0] ? String(leaveTypes[0].id) : '',
        start_date: '',
        end_date: '',
        reason: '',
        attachment: null,
    });

    const formErrors = form.errors as Record<string, string | undefined>;
    const selectedLeaveType = leaveTypes.find((type) => String(type.id) === form.data.leave_type_id) ?? null;

    const aggregateTotals = leaveBalances.reduce(
        (acc, item) => ({
            total: acc.total + item.total_quota,
            used: acc.used + item.used_quota,
            pending: acc.pending + item.pending_quota,
            remaining: acc.remaining + item.remaining_quota,
        }),
        { total: 0, used: 0, pending: 0, remaining: 0 },
    );

    const handleSubmit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.post('/attendance/leave-requests', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setIsDialogOpen(false);
                form.reset('start_date', 'end_date', 'reason', 'attachment');
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Leave Requests" />

            <div className="flex flex-1 flex-col gap-6 p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Leave Balances &amp; Requests ({currentYear})</h1>
                        <p className="text-muted-foreground text-sm">
                            Track your annual quota allocation, pending reservations, and multi-level leave approvals.
                        </p>
                    </div>

                    {hasEmployeeProfile && (
                        <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
                            <DialogTrigger asChild>
                                <Button type="button">
                                    <CalendarPlus className="size-4" />
                                    Submit Leave Request
                                </Button>
                            </DialogTrigger>

                            <DialogContent className="sm:max-w-lg">
                                <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                                    <DialogHeader>
                                        <DialogTitle>New Leave Application</DialogTitle>
                                        <DialogDescription>
                                            Select a leave type and date range. Submitting will reserve pending quota until reviewed.
                                        </DialogDescription>
                                    </DialogHeader>

                                    {formErrors.leave && (
                                        <Alert variant="destructive">
                                            <AlertCircle className="size-4" />
                                            <AlertTitle>Cannot Submit Leave</AlertTitle>
                                            <AlertDescription>{formErrors.leave}</AlertDescription>
                                        </Alert>
                                    )}

                                    <div className="grid gap-2">
                                        <Label htmlFor="leave_type_id">Leave Type</Label>
                                        <Select
                                            value={form.data.leave_type_id}
                                            onValueChange={(value) => form.setData('leave_type_id', value)}
                                        >
                                            <SelectTrigger id="leave_type_id">
                                                <SelectValue placeholder="Choose leave category" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {leaveTypes.map((type) => (
                                                    <SelectItem key={type.id} value={String(type.id)}>
                                                        {type.name} ({type.code}){type.is_paid ? '' : ' • Unpaid'}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {formErrors.leave_type_id && (
                                            <p className="text-destructive text-xs">{formErrors.leave_type_id}</p>
                                        )}
                                    </div>

                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="start_date">Start Date</Label>
                                            <Input
                                                id="start_date"
                                                type="date"
                                                value={form.data.start_date}
                                                onChange={(e) => form.setData('start_date', e.target.value)}
                                                required
                                            />
                                            {formErrors.start_date && (
                                                <p className="text-destructive text-xs">{formErrors.start_date}</p>
                                            )}
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="end_date">End Date</Label>
                                            <Input
                                                id="end_date"
                                                type="date"
                                                min={form.data.start_date || undefined}
                                                value={form.data.end_date}
                                                onChange={(e) => form.setData('end_date', e.target.value)}
                                                required
                                            />
                                            {formErrors.end_date && (
                                                <p className="text-destructive text-xs">{formErrors.end_date}</p>
                                            )}
                                        </div>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="reason">Reason</Label>
                                        <textarea
                                            id="reason"
                                            rows={3}
                                            value={form.data.reason}
                                            onChange={(e) => form.setData('reason', e.target.value)}
                                            placeholder="Provide details for your manager and HR review..."
                                            required
                                            className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 flex w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                                        />
                                        {formErrors.reason && (
                                            <p className="text-destructive text-xs">{formErrors.reason}</p>
                                        )}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="attachment">
                                            Supporting Attachment{' '}
                                            {selectedLeaveType?.requires_attachment ? (
                                                <span className="text-destructive font-medium">(Required)</span>
                                            ) : (
                                                <span className="text-muted-foreground font-normal">(Optional — PDF/JPG/PNG, max 5MB)</span>
                                            )}
                                        </Label>
                                        <Input
                                            id="attachment"
                                            type="file"
                                            accept=".pdf,.jpg,.jpeg,.png"
                                            required={Boolean(selectedLeaveType?.requires_attachment)}
                                            onChange={(e) => {
                                                const selectedFile = e.target.files?.[0] ?? null;
                                                form.setData('attachment', selectedFile);
                                            }}
                                        />
                                        {formErrors.attachment && (
                                            <p className="text-destructive text-xs">{formErrors.attachment}</p>
                                        )}
                                    </div>

                                    <DialogFooter className="pt-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setIsDialogOpen(false)}
                                            disabled={form.processing}
                                        >
                                            Cancel
                                        </Button>
                                        <Button type="submit" disabled={form.processing || leaveTypes.length === 0}>
                                            {form.processing ? 'Submitting...' : 'Submit Request'}
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                    )}
                </div>

                {flash?.success && (
                    <Alert className="border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-300">
                        <CheckCircle2 className="size-4 text-emerald-600 dark:text-emerald-400" />
                        <AlertTitle>Success</AlertTitle>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}

                {!hasEmployeeProfile && (
                    <Alert variant="destructive">
                        <AlertCircle className="size-4" />
                        <AlertTitle>No Linked Employee Profile</AlertTitle>
                        <AlertDescription>
                            Your account is not linked to an employee profile yet. Please contact HR Administration to initialize your leave quota.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Total Quota ({currentYear})</CardDescription>
                            <CardTitle className="text-3xl tabular-nums">{aggregateTotals.total} days</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-muted-foreground text-xs">Combined entitlement across active leave types</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Used Quota</CardDescription>
                            <CardTitle className="text-3xl tabular-nums">{aggregateTotals.used} days</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-muted-foreground text-xs">Approved leave days deducted this year</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Pending / Reserved</CardDescription>
                            <CardTitle className="text-3xl text-amber-600 tabular-nums dark:text-amber-400">
                                {aggregateTotals.pending} days
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-muted-foreground text-xs">Locked while awaiting approval</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Remaining Quota</CardDescription>
                            <CardTitle className="text-3xl text-emerald-600 tabular-nums dark:text-emerald-400">
                                {aggregateTotals.remaining} days
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-muted-foreground text-xs">Available to request right now</p>
                        </CardContent>
                    </Card>
                </div>

                {leaveBalances.length > 0 && (
                    <div className="grid gap-4 md:grid-cols-3">
                        {leaveBalances.map((balance) => (
                            <Card key={balance.id}>
                                <CardHeader className="pb-3">
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="text-base">{balance.leave_type?.name ?? 'Leave Quota'}</CardTitle>
                                        <Badge variant="outline">{balance.leave_type?.code ?? balance.year}</Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="grid grid-cols-4 gap-2 text-center text-xs">
                                    <div className="bg-muted/40 rounded-md p-2">
                                        <p className="text-muted-foreground">Total</p>
                                        <p className="mt-1 text-sm font-semibold">{balance.total_quota}</p>
                                    </div>
                                    <div className="bg-muted/40 rounded-md p-2">
                                        <p className="text-muted-foreground">Used</p>
                                        <p className="mt-1 text-sm font-semibold">{balance.used_quota}</p>
                                    </div>
                                    <div className="bg-muted/40 rounded-md p-2">
                                        <p className="text-muted-foreground">Pending</p>
                                        <p className="mt-1 text-sm font-semibold text-amber-600 dark:text-amber-400">
                                            {balance.pending_quota}
                                        </p>
                                    </div>
                                    <div className="bg-muted/40 rounded-md p-2">
                                        <p className="text-muted-foreground">Available</p>
                                        <p className="mt-1 text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                                            {balance.remaining_quota}
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Leave Request History</CardTitle>
                        <CardDescription>
                            All submitted leave applications and approval decisions.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto border-t">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 border-b text-xs uppercase">
                                    <tr>
                                        <th className="px-4 py-3">Leave Type</th>
                                        <th className="px-4 py-3">Date Range</th>
                                        <th className="px-4 py-3">Days</th>
                                        <th className="px-4 py-3">Reason</th>
                                        <th className="px-4 py-3">Attachment</th>
                                        <th className="px-4 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {leaveRequests.length === 0 ? (
                                        <tr>
                                            <td colSpan={6} className="text-muted-foreground px-4 py-8 text-center">
                                                No leave requests submitted yet.
                                            </td>
                                        </tr>
                                    ) : (
                                        leaveRequests.map((requestItem) => (
                                            <tr key={requestItem.id} className="hover:bg-muted/30 transition-colors">
                                                <td className="px-4 py-3 font-medium">
                                                    {requestItem.leave_type?.name ?? `Type #${requestItem.leave_type_id}`}
                                                </td>
                                                <td className="px-4 py-3 font-mono text-xs">
                                                    {formatDateRange(requestItem.start_date, requestItem.end_date)}
                                                </td>
                                                <td className="px-4 py-3">{requestItem.total_days} day(s)</td>
                                                <td className="max-w-xs px-4 py-3">
                                                    <p className="truncate">{requestItem.reason}</p>
                                                    {requestItem.rejection_reason && (
                                                        <p className="text-destructive mt-0.5 text-xs">
                                                            Note: {requestItem.rejection_reason}
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {requestItem.attachment_path ? (
                                                        <span className="text-muted-foreground inline-flex items-center gap-1 text-xs">
                                                            <Paperclip className="size-3.5" />
                                                            Attached
                                                        </span>
                                                    ) : (
                                                        <span className="text-muted-foreground text-xs">-</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Badge variant="outline" className={getLeaveStatusBadgeClass(requestItem.status)}>
                                                        {requestItem.status}
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
            </div>
        </AppLayout>
    );
}
