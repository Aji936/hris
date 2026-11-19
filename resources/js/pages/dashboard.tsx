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
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    CalendarDays,
    Clock,
    Download,
    Megaphone,
    Pin,
    Plus,
    ReceiptText,
    ShieldCheck,
    Users,
} from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

export type AnnouncementCategoryValue = 'INFO' | 'POLICY' | 'EVENT' | 'URGENT';

export interface AnnouncementItem {
    id: number;
    title: string;
    category: AnnouncementCategoryValue;
    category_label: string;
    content: string;
    attachment_path: string | null;
    attachment_url: string | null;
    is_pinned: boolean;
    is_active: boolean;
    published_at: string | null;
    published_date: string | null;
    expires_at: string | null;
    expires_date: string | null;
    author_name: string;
}

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
    announcements?: AnnouncementItem[];
    canManageAnnouncements?: boolean;
}

export function getAnnouncementCategoryBadgeClass(category: AnnouncementCategoryValue): string {
    switch (category) {
        case 'URGENT':
            return 'border-rose-500/40 bg-rose-500/10 text-rose-700 dark:text-rose-300';
        case 'POLICY':
            return 'border-indigo-500/40 bg-indigo-500/10 text-indigo-700 dark:text-indigo-300';
        case 'EVENT':
            return 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
        case 'INFO':
        default:
            return 'border-sky-500/40 bg-sky-500/10 text-sky-700 dark:text-sky-300';
    }
}

export default function Dashboard({
    approachingRetirements = [],
    announcements = [],
    canManageAnnouncements = false,
}: DashboardPageProps) {
    const { auth } = usePage<SharedData>().props;
    const roles = auth.roles ?? [];
    const permissions = auth.permissions ?? [];
    const canViewDirectory = permissions.includes('employee.view');

    const [selectedAnnouncement, setSelectedAnnouncement] = useState<AnnouncementItem | null>(null);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-5 overflow-x-hidden p-3.5 sm:gap-6 sm:p-6">
                <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight sm:text-2xl">
                            Welcome back, {auth.user.name}
                        </h1>
                        <p className="text-muted-foreground text-xs sm:text-sm">
                            Employee Self-Service (ESS) portal for daily attendance, leave balances, company announcements, and official payslips.
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

                {/* Company Announcements / Informasi & Pengumuman Perusahaan Widget */}
                <Card className="border-primary/20 shadow-xs">
                    <CardHeader className="flex flex-col justify-between gap-3 p-4 sm:flex-row sm:items-center sm:p-6">
                        <div>
                            <CardTitle className="flex items-center gap-2 text-base sm:text-lg">
                                <Megaphone className="text-primary size-5 shrink-0" />
                                Informasi &amp; Pengumuman Perusahaan
                            </CardTitle>
                            <CardDescription className="text-xs sm:text-sm">
                                Berita terbaru, kebijakan HR, dan pengumuman resmi perusahaan. Klik kartu untuk membaca detail.
                            </CardDescription>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            {canManageAnnouncements ? (
                                <Button asChild size="sm" className="min-h-9 w-full sm:w-auto">
                                    <Link href="/announcements">
                                        <Plus className="size-4" />
                                        + Buat / Kelola Pengumuman
                                    </Link>
                                </Button>
                            ) : (
                                <Button asChild variant="outline" size="sm" className="min-h-9 w-full sm:w-auto">
                                    <Link href="/announcements">
                                        Lihat Semua
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent className="p-4 pt-0 sm:p-6 sm:pt-0">
                        {announcements.length === 0 ? (
                            <div className="text-muted-foreground rounded-lg border border-dashed p-6 text-center text-xs sm:text-sm">
                                Belum ada pengumuman perusahaan yang aktif saat ini.
                            </div>
                        ) : (
                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                {announcements.map((item) => (
                                    <button
                                        key={item.id}
                                        type="button"
                                        onClick={() => setSelectedAnnouncement(item)}
                                        className={`hover:border-primary/40 hover:bg-muted/30 group flex flex-col justify-between gap-2.5 rounded-xl border p-3.5 text-left transition-all ${
                                            item.is_pinned ? 'border-amber-500/40 bg-amber-500/[0.03]' : ''
                                        }`}
                                    >
                                        <div className="w-full space-y-1.5">
                                            <div className="flex flex-wrap items-center justify-between gap-1.5">
                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    <Badge
                                                        variant="outline"
                                                        className={`text-[11px] ${getAnnouncementCategoryBadgeClass(item.category)}`}
                                                    >
                                                        {item.category_label}
                                                    </Badge>
                                                    {item.is_pinned && (
                                                        <Badge
                                                            variant="outline"
                                                            className="gap-1 border-amber-500/40 bg-amber-500/10 text-[11px] text-amber-700 dark:text-amber-300"
                                                        >
                                                            <Pin className="size-3" />
                                                            Disematkan
                                                        </Badge>
                                                    )}
                                                </div>
                                                <span className="text-muted-foreground font-mono text-[11px]">
                                                    {item.published_date ?? '-'}
                                                </span>
                                            </div>
                                            <h3 className="group-hover:text-primary line-clamp-1 text-sm font-semibold sm:text-base">
                                                {item.title}
                                            </h3>
                                            <p className="text-muted-foreground line-clamp-2 text-xs leading-relaxed sm:text-sm">
                                                {item.content}
                                            </p>
                                        </div>

                                        <div className="flex w-full items-center justify-between border-t pt-2 text-[11px]">
                                            <span className="text-muted-foreground truncate">
                                                Oleh: <strong className="font-medium">{item.author_name}</strong>
                                            </span>
                                            <span className="text-primary inline-flex items-center gap-1 font-medium">
                                                Baca Selengkapnya
                                                <ArrowRight className="size-3" />
                                            </span>
                                        </div>
                                    </button>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

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
                            <CardTitle>Leave &amp; Permit</CardTitle>
                            <CardDescription>
                                Check remaining annual quota, submit new leave or permit applications, and print official PDF forms.
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

            {/* Modal Baca Detail Pengumuman (Mobile-Friendly) */}
            <Dialog
                open={selectedAnnouncement !== null}
                onOpenChange={(open) => !open && setSelectedAnnouncement(null)}
            >
                <DialogContent className="max-h-[85vh] w-[95vw] max-w-full overflow-y-auto p-3.5 sm:max-w-xl sm:p-6">
                    {selectedAnnouncement && (
                        <div className="flex flex-col gap-4">
                            <DialogHeader className="sticky top-0 z-10 border-b bg-white pb-2.5 dark:bg-slate-950">
                                <div className="mb-1.5 flex flex-wrap items-center gap-1.5 pr-6">
                                    <Badge
                                        variant="outline"
                                        className={getAnnouncementCategoryBadgeClass(selectedAnnouncement.category)}
                                    >
                                        {selectedAnnouncement.category_label}
                                    </Badge>
                                    {selectedAnnouncement.is_pinned && (
                                        <Badge
                                            variant="outline"
                                            className="gap-1 border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300"
                                        >
                                            <Pin className="size-3" />
                                            Disematkan
                                        </Badge>
                                    )}
                                </div>
                                <DialogTitle className="text-base leading-snug sm:text-lg">
                                    {selectedAnnouncement.title}
                                </DialogTitle>
                                <DialogDescription className="text-xs">
                                    Diterbitkan tanggal {selectedAnnouncement.published_date ?? '-'} oleh{' '}
                                    <strong>{selectedAnnouncement.author_name}</strong>
                                </DialogDescription>
                            </DialogHeader>

                            <div className="bg-muted/20 rounded-lg border p-3.5 text-sm leading-relaxed whitespace-pre-line">
                                {selectedAnnouncement.content}
                            </div>

                            <DialogFooter className="sticky bottom-0 z-10 flex flex-col gap-2 border-t bg-white pt-2.5 sm:flex-row sm:justify-between dark:bg-slate-950">
                                {selectedAnnouncement.attachment_url ? (
                                    <Button asChild variant="outline" size="sm" className="min-h-9 w-full sm:w-auto">
                                        <a
                                            href={selectedAnnouncement.attachment_url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <Download className="size-4" />
                                            Unduh Lampiran
                                        </a>
                                    </Button>
                                ) : (
                                    <div />
                                )}
                                <Button
                                    type="button"
                                    size="sm"
                                    className="min-h-9 w-full sm:w-auto"
                                    onClick={() => setSelectedAnnouncement(null)}
                                >
                                    Tutup
                                </Button>
                            </DialogFooter>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
