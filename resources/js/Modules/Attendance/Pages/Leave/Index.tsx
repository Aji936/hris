import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import AppLayout from '@/layouts/app-layout';
import {
    type BreadcrumbItem,
    type LeaveBalanceProps,
    type LeavePermitTypeValue,
    type LeaveRequestItem,
    type LeaveRequestStatusValue,
    type SharedData,
} from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    CalendarPlus,
    CheckCircle2,
    Eye,
    FileCheck,
    Paperclip,
    Printer,
} from 'lucide-react';
import { type FormEvent, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Leave & Permit Application',
        href: '/attendance/leave-requests',
    },
];

const APPLICATION_TYPES: Array<{
    value: LeavePermitTypeValue;
    label: string;
    sublabel: string;
    usesTime: boolean;
}> = [
    { value: 'SICK', label: '1. Sick', sublabel: 'Sakit (Surat Dokter)', usesTime: false },
    { value: 'SPECIAL_PERMISSION', label: '2. Special Permission', sublabel: 'Izin Khusus', usesTime: false },
    { value: 'ANNUAL_LEAVE', label: '3. Annual Leave', sublabel: 'Cuti Tahunan', usesTime: false },
    { value: 'LATE_COMING', label: '4. Late Coming', sublabel: 'Datang Terlambat', usesTime: true },
    { value: 'EARLY_LEAVING', label: '5. Early Leaving', sublabel: 'Pulang Awal', usesTime: true },
    { value: 'NO_ABSENT_RECORD', label: '6. No Absent Record', sublabel: 'Lupa Absen', usesTime: true },
];

type LeaveFormState = {
    leave_type_id: string;
    application_type: LeavePermitTypeValue;
    start_date: string;
    end_date: string;
    start_time: string;
    end_time: string;
    reason: string;
    attachment: File | null;
};

type ApprovalReviewFormState = {
    level: number;
    previous_leave_balance: string;
    current_leave_taken: string;
    remaining_leave_balance: string;
    is_rejected: boolean;
    rejection_reason: string;
    hrd_other_notes: string;
};

function getLeaveStatusBadgeClass(status: LeaveRequestStatusValue): string {
    switch (status) {
        case 'APPROVED':
        case 'APPROVED_HRD':
            return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400';
        case 'APPROVED_L1':
        case 'APPROVED_L2':
            return 'border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-400';
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
    employeeProfile = null,
    canApprove = false,
    isHrd = false,
    leaveBalances,
    leaveTypes,
    leaveRequests,
}: LeaveBalanceProps) {
    const page = usePage<SharedData>();
    const flash = page.props.flash;
    const todayDate = new Date().toISOString().slice(0, 10);

    const [isDialogOpen, setIsDialogOpen] = useState<boolean>(false);
    const [selectedRequest, setSelectedRequest] = useState<LeaveRequestItem | null>(null);

    const findMatchingLeaveTypeId = (appType: LeavePermitTypeValue): string => {
        const preferredCode = appType === 'ANNUAL_LEAVE' ? 'ANNUAL' : appType;
        const matched = leaveTypes.find(
            (t) => t.code.toUpperCase() === preferredCode || t.code.toUpperCase() === appType,
        );
        return matched ? String(matched.id) : leaveTypes[0] ? String(leaveTypes[0].id) : '';
    };

    const form = useForm<LeaveFormState>({
        leave_type_id: findMatchingLeaveTypeId('ANNUAL_LEAVE'),
        application_type: 'ANNUAL_LEAVE',
        start_date: todayDate,
        end_date: todayDate,
        start_time: '',
        end_time: '',
        reason: '',
        attachment: null,
    });

    const reviewForm = useForm<ApprovalReviewFormState>({
        level: 1,
        previous_leave_balance: '12',
        current_leave_taken: '1',
        remaining_leave_balance: '11',
        is_rejected: false,
        rejection_reason: '',
        hrd_other_notes: '',
    });

    const formErrors = form.errors as Record<string, string | undefined>;
    const reviewErrors = reviewForm.errors as Record<string, string | undefined>;

    const selectedLeaveType = leaveTypes.find((type) => String(type.id) === form.data.leave_type_id) ?? null;
    const selectedPermitMeta = APPLICATION_TYPES.find((item) => item.value === form.data.application_type);

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
                form.reset('start_time', 'end_time', 'reason', 'attachment');
            },
        });
    };

    const openReviewModal = (reqItem: LeaveRequestItem): void => {
        setSelectedRequest(reqItem);
        const prevBal = reqItem.previous_leave_balance ?? aggregateTotals.remaining;
        const currTaken = reqItem.current_leave_taken ?? reqItem.total_days;
        const remBal = reqItem.remaining_leave_balance ?? prevBal - currTaken;

        const nextSuggestedLevel =
            reqItem.status === 'PENDING' ? 1 : reqItem.status === 'APPROVED_L1' ? 2 : 3;

        reviewForm.setData({
            level: isHrd && reqItem.status === 'APPROVED_L2' ? 3 : nextSuggestedLevel,
            previous_leave_balance: String(prevBal),
            current_leave_taken: String(currTaken),
            remaining_leave_balance: String(remBal),
            is_rejected: Boolean(reqItem.is_rejected),
            rejection_reason: reqItem.rejection_reason ?? '',
            hrd_other_notes: reqItem.hrd_other_notes ?? '',
        });
        reviewForm.clearErrors();
    };

    const submitApprovalLevel = (level: number): void => {
        if (!selectedRequest) {
            return;
        }

        reviewForm.transform((data) => ({
            ...data,
            level,
            previous_leave_balance: Number(data.previous_leave_balance || 0),
            current_leave_taken: Number(data.current_leave_taken || 0),
            remaining_leave_balance: Number(data.remaining_leave_balance || 0),
        }));

        reviewForm.post(`/attendance/leave-requests/${selectedRequest.id}/approve`, {
            preserveScroll: true,
            onSuccess: () => setSelectedRequest(null),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Leave & Permit Application" />

            <div className="flex flex-1 flex-col gap-4 overflow-x-hidden p-3.5 sm:gap-6 sm:p-6">
                <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-lg font-semibold tracking-tight sm:text-2xl">
                            Leave &amp; Permit Application ({currentYear})
                        </h1>
                        <p className="text-muted-foreground text-xs sm:text-sm">
                            Official leave &amp; permit submission, HRD leave calculation, 3-level approval workflow, and printable PDF form.
                        </p>
                    </div>

                    {hasEmployeeProfile && (
                        <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
                            <DialogTrigger asChild>
                                <Button type="button" size="lg" className="min-h-11 w-full sm:w-auto">
                                    <CalendarPlus className="size-4" />
                                    New Leave / Permit Application
                                </Button>
                            </DialogTrigger>

                            <DialogContent className="max-h-[88vh] w-[95vw] max-w-full overflow-y-auto p-3.5 sm:max-w-2xl sm:p-6">
                                <form onSubmit={handleSubmit} className="flex flex-col gap-3.5 sm:gap-4">
                                    <DialogHeader className="sticky top-0 z-10 border-b bg-white pb-2.5 dark:bg-slate-950">
                                        <DialogTitle className="text-base sm:text-lg">
                                            LEAVE &amp; PERMIT APPLICATION
                                        </DialogTitle>
                                        <DialogDescription className="text-xs sm:text-sm">
                                            Formulir Permohonan Cuti &amp; Izin Karyawan. Identitas pemohon terisi otomatis.
                                        </DialogDescription>
                                    </DialogHeader>

                                    {formErrors.leave && (
                                        <Alert variant="destructive">
                                            <AlertCircle className="size-4" />
                                            <AlertTitle>Cannot Submit Application</AlertTitle>
                                            <AlertDescription>{formErrors.leave}</AlertDescription>
                                        </Alert>
                                    )}

                                    {/* Read-Only Applicant Identity Header */}
                                    <div className="bg-muted/40 grid grid-cols-1 gap-2 rounded-lg border p-2.5 text-xs sm:grid-cols-2 sm:gap-4 sm:text-sm">
                                        <div className="flex items-center justify-between sm:block">
                                            <p className="text-muted-foreground">Date / Tanggal</p>
                                            <p className="font-mono font-semibold sm:mt-0.5">{todayDate}</p>
                                        </div>
                                        <div className="flex items-center justify-between sm:block">
                                            <p className="text-muted-foreground">Name / Nama</p>
                                            <p className="truncate font-semibold sm:mt-0.5">
                                                {employeeProfile?.full_name ?? page.props.auth.user.name}
                                            </p>
                                        </div>
                                        <div className="flex items-center justify-between sm:block">
                                            <p className="text-muted-foreground">Employee No / NIK</p>
                                            <p className="font-mono font-semibold sm:mt-0.5">
                                                {employeeProfile?.employee_code ?? '-'}
                                            </p>
                                        </div>
                                        <div className="flex items-center justify-between sm:block">
                                            <p className="text-muted-foreground">Join Date / Tgl Masuk</p>
                                            <p className="font-mono font-semibold sm:mt-0.5">
                                                {employeeProfile?.join_date ?? '-'}
                                            </p>
                                        </div>
                                    </div>

                                    {/* 6 Application Types Checkboxes / Radio Cards */}
                                    <div className="grid gap-1.5">
                                        <Label className="text-xs sm:text-sm">
                                            Application Type / Jenis Permohonan
                                        </Label>
                                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                            {APPLICATION_TYPES.map((item) => {
                                                const isChecked = form.data.application_type === item.value;
                                                return (
                                                    <label
                                                        key={item.value}
                                                        className={`flex cursor-pointer items-start gap-2 rounded-lg border px-2.5 py-2 text-xs transition-colors sm:text-sm ${
                                                            isChecked
                                                                ? 'border-primary bg-primary/5 ring-primary/30 ring-1'
                                                                : 'hover:bg-muted/40'
                                                        }`}
                                                    >
                                                        <input
                                                            type="radio"
                                                            name="application_type"
                                                            value={item.value}
                                                            checked={isChecked}
                                                            onChange={() => {
                                                                form.setData((prev) => ({
                                                                    ...prev,
                                                                    application_type: item.value,
                                                                    leave_type_id: findMatchingLeaveTypeId(item.value),
                                                                }));
                                                            }}
                                                            className="mt-0.5 shrink-0"
                                                        />
                                                        <div className="min-w-0">
                                                            <p className="truncate font-medium leading-tight">{item.label}</p>
                                                            <p className="text-muted-foreground mt-0.5 truncate text-[11px] sm:text-xs">
                                                                {item.sublabel}
                                                            </p>
                                                        </div>
                                                    </label>
                                                );
                                            })}
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="start_date" className="text-xs sm:text-sm">
                                                Start Date / Tanggal Mulai
                                            </Label>
                                            <Input
                                                id="start_date"
                                                type="date"
                                                className="w-full max-w-full text-sm sm:text-base"
                                                value={form.data.start_date}
                                                onChange={(e) => form.setData('start_date', e.target.value)}
                                                required
                                            />
                                            {formErrors.start_date && (
                                                <p className="text-destructive text-xs">{formErrors.start_date}</p>
                                            )}
                                        </div>

                                        <div className="grid gap-1.5">
                                            <Label htmlFor="end_date" className="text-xs sm:text-sm">
                                                End Date / Tanggal Selesai
                                            </Label>
                                            <Input
                                                id="end_date"
                                                type="date"
                                                className="w-full max-w-full text-sm sm:text-base"
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

                                    {selectedPermitMeta?.usesTime && (
                                        <div className="grid grid-cols-2 gap-2.5 sm:gap-4">
                                            <div className="grid gap-1.5">
                                                <Label htmlFor="start_time" className="text-xs sm:text-sm">
                                                    Start Time / Jam Mulai
                                                </Label>
                                                <Input
                                                    id="start_time"
                                                    type="time"
                                                    className="w-full max-w-full text-sm sm:text-base"
                                                    value={form.data.start_time}
                                                    onChange={(e) => form.setData('start_time', e.target.value)}
                                                />
                                            </div>
                                            <div className="grid gap-1.5">
                                                <Label htmlFor="end_time" className="text-xs sm:text-sm">
                                                    End Time / Jam Selesai
                                                </Label>
                                                <Input
                                                    id="end_time"
                                                    type="time"
                                                    className="w-full max-w-full text-sm sm:text-base"
                                                    value={form.data.end_time}
                                                    onChange={(e) => form.setData('end_time', e.target.value)}
                                                />
                                            </div>
                                        </div>
                                    )}

                                    <div className="grid gap-1.5">
                                        <Label htmlFor="reason" className="text-xs sm:text-sm">
                                            Reason / Contents (Alasan Permohonan)
                                        </Label>
                                        <textarea
                                            id="reason"
                                            rows={3}
                                            value={form.data.reason}
                                            onChange={(e) => form.setData('reason', e.target.value)}
                                            placeholder="Tuliskan alasan lengkap pengajuan cuti / izin..."
                                            required
                                            className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 flex w-full max-w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] sm:text-base"
                                        />
                                        {formErrors.reason && (
                                            <p className="text-destructive text-xs">{formErrors.reason}</p>
                                        )}
                                    </div>

                                    <div className="grid gap-1.5">
                                        <Label htmlFor="attachment" className="text-xs sm:text-sm">
                                            Supporting Attachment{' '}
                                            {selectedLeaveType?.requires_attachment && form.data.application_type === 'SICK' ? (
                                                <span className="text-destructive font-medium">(Required — Surat Dokter)</span>
                                            ) : (
                                                <span className="text-muted-foreground font-normal">(Optional — PDF/JPG/PNG)</span>
                                            )}
                                        </Label>
                                        <Input
                                            id="attachment"
                                            type="file"
                                            accept=".pdf,.jpg,.jpeg,.png"
                                            className="w-full max-w-full text-sm sm:text-base"
                                            onChange={(e) => {
                                                const selectedFile = e.target.files?.[0] ?? null;
                                                form.setData('attachment', selectedFile);
                                            }}
                                        />
                                        {formErrors.attachment && (
                                            <p className="text-destructive text-xs">{formErrors.attachment}</p>
                                        )}
                                    </div>

                                    <DialogFooter className="sticky bottom-0 z-10 border-t bg-white pt-2.5 dark:bg-slate-950">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="min-h-10 w-full sm:w-auto"
                                            onClick={() => setIsDialogOpen(false)}
                                            disabled={form.processing}
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            type="submit"
                                            className="min-h-10 w-full sm:w-auto"
                                            disabled={form.processing}
                                        >
                                            {form.processing ? 'Submitting...' : 'Sign & Submit Application'}
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

                <div className="grid grid-cols-2 gap-2.5 sm:gap-4 lg:grid-cols-4">
                    <Card className="p-3 sm:p-0">
                        <CardHeader className="p-0 pb-1.5 sm:p-6 sm:pb-2">
                            <CardDescription className="text-xs">Total Quota ({currentYear})</CardDescription>
                            <CardTitle className="text-xl tabular-nums sm:text-3xl">{aggregateTotals.total} days</CardTitle>
                        </CardHeader>
                        <CardContent className="hidden sm:block">
                            <p className="text-muted-foreground text-xs">Combined entitlement across active leave types</p>
                        </CardContent>
                    </Card>

                    <Card className="p-3 sm:p-0">
                        <CardHeader className="p-0 pb-1.5 sm:p-6 sm:pb-2">
                            <CardDescription className="text-xs">Used Quota</CardDescription>
                            <CardTitle className="text-xl tabular-nums sm:text-3xl">{aggregateTotals.used} days</CardTitle>
                        </CardHeader>
                        <CardContent className="hidden sm:block">
                            <p className="text-muted-foreground text-xs">Approved leave days deducted this year</p>
                        </CardContent>
                    </Card>

                    <Card className="p-3 sm:p-0">
                        <CardHeader className="p-0 pb-1.5 sm:p-6 sm:pb-2">
                            <CardDescription className="text-xs">Pending / Reserved</CardDescription>
                            <CardTitle className="text-xl text-amber-600 tabular-nums sm:text-3xl dark:text-amber-400">
                                {aggregateTotals.pending} days
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="hidden sm:block">
                            <p className="text-muted-foreground text-xs">Locked while awaiting approval</p>
                        </CardContent>
                    </Card>

                    <Card className="p-3 sm:p-0">
                        <CardHeader className="p-0 pb-1.5 sm:p-6 sm:pb-2">
                            <CardDescription className="text-xs">Remaining Quota</CardDescription>
                            <CardTitle className="text-xl text-emerald-600 tabular-nums sm:text-3xl dark:text-emerald-400">
                                {aggregateTotals.remaining} days
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="hidden sm:block">
                            <p className="text-muted-foreground text-xs">Available to request right now</p>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="p-3.5 sm:p-6">
                        <CardTitle className="text-base sm:text-lg">Leave &amp; Permit Applications</CardTitle>
                        <CardDescription className="text-xs sm:text-sm">
                            Track 3-level approval signatures (Assistant Head → Dept. Manager → HRD) and print official PDF forms.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        {/* Mobile Card List View (< 640px) */}
                        <div className="divide-y border-t sm:hidden">
                            {leaveRequests.length === 0 ? (
                                <div className="text-muted-foreground px-3.5 py-6 text-center text-xs">
                                    No leave or permit applications submitted yet.
                                </div>
                            ) : (
                                leaveRequests.map((requestItem) => (
                                    <div key={`mobile-${requestItem.id}`} className="flex flex-col gap-2.5 p-3.5 text-xs">
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-semibold">
                                                    {requestItem.application_type_label ?? requestItem.leave_type?.name ?? 'Annual Leave'}
                                                </p>
                                                <p className="text-muted-foreground truncate">
                                                    {requestItem.employee?.full_name ?? employeeProfile?.full_name ?? `Emp #${requestItem.employee_id}`}
                                                    {' • '}
                                                    <span className="font-mono">
                                                        {requestItem.applicant_employee_code ?? requestItem.employee?.employee_code ?? '-'}
                                                    </span>
                                                </p>
                                            </div>
                                            <Badge variant="outline" className={getLeaveStatusBadgeClass(requestItem.status)}>
                                                {requestItem.status}
                                            </Badge>
                                        </div>

                                        <div className="bg-muted/30 grid grid-cols-2 gap-2 rounded-md border p-2 font-mono text-[11px]">
                                            <div>
                                                <span className="text-muted-foreground block font-sans">Date / Time</span>
                                                <span>{formatDateRange(requestItem.start_date, requestItem.end_date)} ({requestItem.total_days}d)</span>
                                                {(requestItem.start_time || requestItem.end_time) && (
                                                    <span className="text-muted-foreground block">
                                                        {requestItem.start_time ?? '-'} - {requestItem.end_time ?? '-'}
                                                    </span>
                                                )}
                                            </div>
                                            <div>
                                                <span className="text-muted-foreground block font-sans">HRD Balance</span>
                                                <span>
                                                    {requestItem.previous_leave_balance ?? '-'} /{' '}
                                                    {requestItem.current_leave_taken ?? requestItem.total_days} /{' '}
                                                    <strong className="text-emerald-600 dark:text-emerald-400">
                                                        {requestItem.remaining_leave_balance ?? '-'}
                                                    </strong>
                                                </span>
                                            </div>
                                        </div>

                                        <p className="text-muted-foreground line-clamp-2">{requestItem.reason}</p>

                                        {requestItem.rejection_reason && (
                                            <p className="text-destructive text-xs font-medium">
                                                Rejected: {requestItem.rejection_reason}
                                            </p>
                                        )}

                                        <div className="flex items-center gap-2 pt-1">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                className="min-h-9 flex-1 text-xs"
                                                onClick={() => openReviewModal(requestItem)}
                                            >
                                                <Eye className="size-3.5" />
                                                {canApprove ? 'Verify / Sign' : 'Details'}
                                            </Button>
                                            <Button asChild variant="outline" size="sm" className="min-h-9 flex-1 text-xs">
                                                <a
                                                    href={`/attendance/leave-permits/${requestItem.id}/print`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                >
                                                    <Printer className="size-3.5" />
                                                    Print PDF
                                                </a>
                                            </Button>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>

                        {/* Desktop / Tablet Table View (>= 640px) wrapped in overflow-x-auto */}
                        <div className="hidden overflow-x-auto border-t sm:block">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 border-b text-xs uppercase">
                                    <tr>
                                        <th className="px-4 py-3">Applicant</th>
                                        <th className="px-4 py-3">Application Type</th>
                                        <th className="px-4 py-3">Date / Time</th>
                                        <th className="px-4 py-3">HRD Calc (Prev / Taken / Rem)</th>
                                        <th className="px-4 py-3">Reason</th>
                                        <th className="px-4 py-3">Status</th>
                                        <th className="px-4 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {leaveRequests.length === 0 ? (
                                        <tr>
                                            <td colSpan={7} className="text-muted-foreground px-4 py-8 text-center">
                                                No leave or permit applications submitted yet.
                                            </td>
                                        </tr>
                                    ) : (
                                        leaveRequests.map((requestItem) => (
                                            <tr key={requestItem.id} className="hover:bg-muted/30 transition-colors">
                                                <td className="px-4 py-3">
                                                    <p className="font-medium">
                                                        {requestItem.employee?.full_name ?? employeeProfile?.full_name ?? `Emp #${requestItem.employee_id}`}
                                                    </p>
                                                    <p className="text-muted-foreground font-mono text-xs">
                                                        {requestItem.applicant_employee_code ?? requestItem.employee?.employee_code ?? '-'}
                                                    </p>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <p className="font-medium">
                                                        {requestItem.application_type_label ?? requestItem.leave_type?.name ?? 'Annual Leave'}
                                                    </p>
                                                    {requestItem.attachment_path && (
                                                        <span className="text-muted-foreground inline-flex items-center gap-1 text-xs">
                                                            <Paperclip className="size-3" />
                                                            Attachment
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 font-mono text-xs">
                                                    <p>{formatDateRange(requestItem.start_date, requestItem.end_date)} ({requestItem.total_days}d)</p>
                                                    {(requestItem.start_time || requestItem.end_time) && (
                                                        <p className="text-muted-foreground">
                                                            Jam: {requestItem.start_time ?? '-'} - {requestItem.end_time ?? '-'}
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 font-mono text-xs">
                                                    {requestItem.previous_leave_balance ?? '-'} /{' '}
                                                    {requestItem.current_leave_taken ?? requestItem.total_days} /{' '}
                                                    <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                                        {requestItem.remaining_leave_balance ?? '-'}
                                                    </span>
                                                </td>
                                                <td className="max-w-xs px-4 py-3">
                                                    <p className="truncate">{requestItem.reason}</p>
                                                    {requestItem.rejection_reason && (
                                                        <p className="text-destructive mt-0.5 text-xs">
                                                            Rejected: {requestItem.rejection_reason}
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Badge variant="outline" className={getLeaveStatusBadgeClass(requestItem.status)}>
                                                        {requestItem.status}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <div className="flex items-center justify-end gap-2">
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            className="min-h-10"
                                                            onClick={() => openReviewModal(requestItem)}
                                                        >
                                                            <Eye className="size-4" />
                                                            {canApprove ? 'Verify / Sign' : 'Details'}
                                                        </Button>
                                                        <Button asChild variant="outline" size="sm" className="min-h-10">
                                                            <a
                                                                href={`/attendance/leave-permits/${requestItem.id}/print`}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                            >
                                                                <Printer className="size-4" />
                                                                Print PDF
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

            {/* Multi-Level Approval, HRD Leave Calculation & 4-Signature Form Modal */}
            <Dialog open={selectedRequest !== null} onOpenChange={(open) => !open && setSelectedRequest(null)}>
                <DialogContent className="max-h-[88vh] w-[95vw] max-w-full overflow-y-auto p-3.5 sm:max-w-2xl sm:p-6">
                    {selectedRequest && (
                        <div className="flex flex-col gap-3.5 sm:gap-4">
                            <DialogHeader className="sticky top-0 z-10 border-b bg-white pb-2.5 dark:bg-slate-950">
                                <div className="flex flex-wrap items-center justify-between gap-2 pr-6">
                                    <DialogTitle className="text-base sm:text-lg">
                                        Leave &amp; Permit Application #{selectedRequest.id}
                                    </DialogTitle>
                                    <Badge variant="outline" className={getLeaveStatusBadgeClass(selectedRequest.status)}>
                                        {selectedRequest.status}
                                    </Badge>
                                </div>
                                <DialogDescription className="text-xs sm:text-sm">
                                    {selectedRequest.application_type_label} •{' '}
                                    {formatDateRange(selectedRequest.start_date, selectedRequest.end_date)} ({selectedRequest.total_days} day(s))
                                </DialogDescription>
                            </DialogHeader>

                            {reviewErrors.leave && (
                                <Alert variant="destructive">
                                    <AlertCircle className="size-4" />
                                    <AlertTitle>Action Failed</AlertTitle>
                                    <AlertDescription>{reviewErrors.leave}</AlertDescription>
                                </Alert>
                            )}

                            {/* Applicant Identity & Reason Summary */}
                            <div className="bg-muted/30 grid grid-cols-1 gap-2 rounded-lg border p-2.5 text-xs sm:grid-cols-2 sm:gap-4 sm:text-sm">
                                <div className="flex items-center justify-between sm:block">
                                    <p className="text-muted-foreground">Date / Tanggal</p>
                                    <p className="font-mono font-semibold sm:mt-0.5">{selectedRequest.application_date ?? '-'}</p>
                                </div>
                                <div className="flex items-center justify-between sm:block">
                                    <p className="text-muted-foreground">Name / Nama</p>
                                    <p className="truncate font-semibold sm:mt-0.5">
                                        {selectedRequest.employee?.full_name ?? employeeProfile?.full_name ?? '-'}
                                    </p>
                                </div>
                                <div className="flex items-center justify-between sm:block">
                                    <p className="text-muted-foreground">Employee No</p>
                                    <p className="font-mono font-semibold sm:mt-0.5">
                                        {selectedRequest.applicant_employee_code ?? selectedRequest.employee?.employee_code ?? '-'}
                                    </p>
                                </div>
                                <div className="flex items-center justify-between sm:block">
                                    <p className="text-muted-foreground">Join Date</p>
                                    <p className="font-mono font-semibold sm:mt-0.5">
                                        {selectedRequest.applicant_hire_date ?? selectedRequest.employee?.join_date ?? '-'}
                                    </p>
                                </div>
                                <div className="border-t pt-2 sm:col-span-2">
                                    <p className="text-muted-foreground">Reason / Contents</p>
                                    <p className="mt-0.5 text-xs font-medium sm:text-sm">{selectedRequest.reason}</p>
                                </div>
                            </div>

                            {/* Calculation of Leave (Fill by HRD) */}
                            <div className="rounded-lg border p-3 sm:p-4">
                                <p className="mb-2.5 text-xs font-semibold uppercase tracking-wider">
                                    Calculation of Leave (Fill by HRD) / Perhitungan Cuti
                                </p>
                                <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-3">
                                    <div className="grid gap-1">
                                        <Label htmlFor="calc_prev" className="text-xs">
                                            Previous Balance (Sisa Awal)
                                        </Label>
                                        <Input
                                            id="calc_prev"
                                            type="number"
                                            disabled={!isHrd}
                                            className="w-full max-w-full text-sm sm:text-base"
                                            value={reviewForm.data.previous_leave_balance}
                                            onChange={(e) => {
                                                const prev = Number(e.target.value || 0);
                                                const taken = Number(reviewForm.data.current_leave_taken || 0);
                                                reviewForm.setData((s) => ({
                                                    ...s,
                                                    previous_leave_balance: e.target.value,
                                                    remaining_leave_balance: String(prev - taken),
                                                }));
                                            }}
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="calc_taken" className="text-xs">
                                            Current Leave Taken (Diambil)
                                        </Label>
                                        <Input
                                            id="calc_taken"
                                            type="number"
                                            disabled={!isHrd}
                                            className="w-full max-w-full text-sm sm:text-base"
                                            value={reviewForm.data.current_leave_taken}
                                            onChange={(e) => {
                                                const prev = Number(reviewForm.data.previous_leave_balance || 0);
                                                const taken = Number(e.target.value || 0);
                                                reviewForm.setData((s) => ({
                                                    ...s,
                                                    current_leave_taken: e.target.value,
                                                    remaining_leave_balance: String(prev - taken),
                                                }));
                                            }}
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="calc_rem" className="text-xs">
                                            Remaining Balance (Sisa Akhir)
                                        </Label>
                                        <Input
                                            id="calc_rem"
                                            type="number"
                                            disabled={!isHrd}
                                            className="w-full max-w-full text-sm sm:text-base"
                                            value={reviewForm.data.remaining_leave_balance}
                                            onChange={(e) => reviewForm.setData('remaining_leave_balance', e.target.value)}
                                        />
                                    </div>
                                </div>

                                <div className="mt-3 grid gap-2.5 border-t pt-3">
                                    <div className="flex items-center space-x-2">
                                        <Checkbox
                                            id="is_rejected_chk"
                                            disabled={!canApprove}
                                            checked={reviewForm.data.is_rejected}
                                            onCheckedChange={(checked) =>
                                                reviewForm.setData('is_rejected', Boolean(checked))
                                            }
                                        />
                                        <Label htmlFor="is_rejected_chk" className="text-destructive text-xs font-semibold sm:text-sm">
                                            REJECTED / Ditolak
                                        </Label>
                                    </div>

                                    {reviewForm.data.is_rejected && (
                                        <div className="grid gap-1">
                                            <Label htmlFor="rej_reason" className="text-xs">
                                                Rejection Reason / Alasan Penolakan
                                            </Label>
                                            <Input
                                                id="rej_reason"
                                                disabled={!canApprove}
                                                className="w-full max-w-full text-sm sm:text-base"
                                                value={reviewForm.data.rejection_reason}
                                                onChange={(e) => reviewForm.setData('rejection_reason', e.target.value)}
                                                placeholder="Tuliskan alasan penolakan..."
                                            />
                                        </div>
                                    )}

                                    <div className="grid gap-1">
                                        <Label htmlFor="hrd_notes" className="text-xs">
                                            Others / Lain-lain (HRD Notes)
                                        </Label>
                                        <Input
                                            id="hrd_notes"
                                            disabled={!canApprove}
                                            className="w-full max-w-full text-sm sm:text-base"
                                            value={reviewForm.data.hrd_other_notes}
                                            onChange={(e) => reviewForm.setData('hrd_other_notes', e.target.value)}
                                            placeholder="Catatan tambahan HRD / Lain-lain..."
                                        />
                                    </div>
                                </div>
                            </div>

                            {/* 4 Aligned Digital Signature Boxes */}
                            <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                <div className="flex h-20 flex-col justify-between rounded-lg border p-2 text-center text-xs sm:h-24 sm:p-2.5">
                                    <div>
                                        <p className="text-muted-foreground font-semibold leading-tight">Yang Mengajukan</p>
                                        <p className="text-muted-foreground text-[10px]">Applicant</p>
                                    </div>
                                    <div>
                                        <p className="truncate font-semibold">
                                            {selectedRequest.employee?.full_name ?? employeeProfile?.full_name ?? 'Applicant'}
                                        </p>
                                        <p className="text-muted-foreground font-mono text-[10px]">
                                            {selectedRequest.applicant_signed_at
                                                ? selectedRequest.applicant_signed_at.slice(0, 10)
                                                : 'Signed'}
                                        </p>
                                    </div>
                                </div>

                                <div className="flex h-20 flex-col justify-between rounded-lg border p-2 text-center text-xs sm:h-24 sm:p-2.5">
                                    <div>
                                        <p className="text-muted-foreground font-semibold leading-tight">Approved I</p>
                                        <p className="text-muted-foreground text-[10px]">Asst. Head of Dept</p>
                                    </div>
                                    <div>
                                        <p className="truncate font-semibold">
                                            {selectedRequest.approver1?.name ?? 'Pending'}
                                        </p>
                                        <p className="text-muted-foreground font-mono text-[10px]">
                                            {selectedRequest.approved_1_at ? selectedRequest.approved_1_at.slice(0, 10) : '-'}
                                        </p>
                                    </div>
                                </div>

                                <div className="flex h-20 flex-col justify-between rounded-lg border p-2 text-center text-xs sm:h-24 sm:p-2.5">
                                    <div>
                                        <p className="text-muted-foreground font-semibold leading-tight">Approved II</p>
                                        <p className="text-muted-foreground text-[10px]">Dept. Manager</p>
                                    </div>
                                    <div>
                                        <p className="truncate font-semibold">
                                            {selectedRequest.approver2?.name ?? 'Pending'}
                                        </p>
                                        <p className="text-muted-foreground font-mono text-[10px]">
                                            {selectedRequest.approved_2_at ? selectedRequest.approved_2_at.slice(0, 10) : '-'}
                                        </p>
                                    </div>
                                </div>

                                <div className="flex h-20 flex-col justify-between rounded-lg border p-2 text-center text-xs sm:h-24 sm:p-2.5">
                                    <div>
                                        <p className="text-muted-foreground font-semibold leading-tight">Approve III</p>
                                        <p className="text-muted-foreground text-[10px]">HRD Verification</p>
                                    </div>
                                    <div>
                                        <p className="truncate font-semibold">
                                            {selectedRequest.approver3?.name ?? selectedRequest.approver?.name ?? 'Pending'}
                                        </p>
                                        <p className="text-muted-foreground font-mono text-[10px]">
                                            {selectedRequest.approved_3_at ? selectedRequest.approved_3_at.slice(0, 10) : '-'}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <DialogFooter className="sticky bottom-0 z-10 flex flex-col gap-2 border-t bg-white pt-2.5 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between dark:bg-slate-950">
                                <Button asChild variant="outline" size="sm" className="min-h-9 w-full sm:w-auto">
                                    <a
                                        href={`/attendance/leave-permits/${selectedRequest.id}/print`}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <Printer className="size-4" />
                                        Cetak / Unduh Formulir PDF
                                    </a>
                                </Button>

                                {canApprove &&
                                    ['PENDING', 'APPROVED_L1', 'APPROVED_L2'].includes(selectedRequest.status) && (
                                        <div className="grid grid-cols-1 gap-2 sm:flex sm:flex-wrap">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                className="min-h-9 w-full sm:w-auto"
                                                disabled={reviewForm.processing}
                                                onClick={() => submitApprovalLevel(1)}
                                            >
                                                <FileCheck className="size-4" />
                                                Approve I (Asst. Head)
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                className="min-h-9 w-full sm:w-auto"
                                                disabled={reviewForm.processing}
                                                onClick={() => submitApprovalLevel(2)}
                                            >
                                                <FileCheck className="size-4" />
                                                Approve II (Manager)
                                            </Button>
                                            {isHrd && (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    className="min-h-9 w-full sm:w-auto"
                                                    variant={reviewForm.data.is_rejected ? 'destructive' : 'default'}
                                                    disabled={reviewForm.processing}
                                                    onClick={() => submitApprovalLevel(3)}
                                                >
                                                    <FileCheck className="size-4" />
                                                    {reviewForm.data.is_rejected
                                                        ? 'Submit Rejection (HRD)'
                                                        : 'Approve III (Finalize HRD)'}
                                                </Button>
                                            )}
                                        </div>
                                    )}
                            </DialogFooter>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
