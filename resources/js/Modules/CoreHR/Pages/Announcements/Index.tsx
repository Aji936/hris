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
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import {
    type AnnouncementCategoryValue,
    type AnnouncementItem,
    getAnnouncementCategoryBadgeClass,
    getXsrfToken,
} from '@/pages/dashboard';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCircle2,
    Clock,
    Download,
    Edit3,
    Eye,
    FileCheck2,
    Megaphone,
    Pin,
    Plus,
    ShieldAlert,
    Sparkles,
    Trash2,
    Users,
} from 'lucide-react';
import { type FormEvent, useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Pengumuman Perusahaan',
        href: '/announcements',
    },
];

const CATEGORIES: Array<{ value: AnnouncementCategoryValue; label: string }> = [
    { value: 'INFO', label: 'Informasi Umum (Info)' },
    { value: 'POLICY', label: 'Kebijakan HR (Policy)' },
    { value: 'EVENT', label: 'Acara & Kegiatan (Event)' },
    { value: 'URGENT', label: 'Penting / Urgent' },
];

type AnnouncementFormState = {
    title: string;
    category: AnnouncementCategoryValue;
    content: string;
    is_pinned: boolean;
    is_active: boolean;
    expires_at: string;
    attachment: File | null;
};

interface AnnouncementsIndexProps {
    announcements: AnnouncementItem[];
    canManageAnnouncements: boolean;
}

export default function AnnouncementsIndex({
    announcements,
    canManageAnnouncements,
}: AnnouncementsIndexProps) {
    const { auth, flash } = usePage<SharedData>().props;

    const [announcementList, setAnnouncementList] = useState<AnnouncementItem[]>(announcements);
    const [viewingAnnouncement, setViewingAnnouncement] = useState<AnnouncementItem | null>(null);
    const [trackingAnnouncement, setTrackingAnnouncement] = useState<AnnouncementItem | null>(null);
    const [trackingFilter, setTrackingFilter] = useState<'ALL' | 'ACKNOWLEDGED' | 'UNREAD'>('ALL');
    const [isAcknowledging, setIsAcknowledging] = useState<boolean>(false);
    const [isFormOpen, setIsFormOpen] = useState<boolean>(false);
    const [editingItem, setEditingItem] = useState<AnnouncementItem | null>(null);

    useEffect(() => {
        setAnnouncementList(announcements);
    }, [announcements]);

    const handleOpenDetailModal = (item: AnnouncementItem): void => {
        const nowFormatted = new Date().toISOString().slice(0, 16).replace('T', ' ');
        const wasUnread = item.is_read === false;

        const updateItemWithRead = (ann: AnnouncementItem, readTime: string | null): AnnouncementItem => {
            const currentRecipients = ann.recipient_statuses ?? [];
            const updatedRecipients = currentRecipients.map((r) =>
                r.user_id === auth.user.id
                    ? { ...r, is_read: true, read_at: r.read_at ?? readTime }
                    : r,
            );
            const nextReadCount = wasUnread ? (ann.read_count ?? 0) + 1 : (ann.read_count ?? 0);

            return {
                ...ann,
                is_read: true,
                read_at_formatted: ann.read_at_formatted ?? readTime,
                read_count: nextReadCount,
                recipient_statuses: updatedRecipients,
            };
        };

        const optimisticItem = updateItemWithRead(item, nowFormatted);
        setViewingAnnouncement(optimisticItem);
        setAnnouncementList((prev) =>
            prev.map((ann) => (ann.id === item.id ? updateItemWithRead(ann, nowFormatted) : ann)),
        );

        void fetch(`/announcements/${item.id}/read`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
        })
            .then(async (res) => {
                if (!res.ok) {
                    return;
                }
                const payload = (await res.json()) as {
                    data?: {
                        read_at_formatted?: string | null;
                        is_acknowledged?: boolean;
                        acknowledged_at_formatted?: string | null;
                    };
                };
                if (payload?.data) {
                    const serverReadTime = payload.data.read_at_formatted ?? nowFormatted;
                    setAnnouncementList((prev) =>
                        prev.map((ann) => (ann.id === item.id ? updateItemWithRead(ann, serverReadTime) : ann)),
                    );
                    setViewingAnnouncement((curr) =>
                        curr && curr.id === item.id ? updateItemWithRead(curr, serverReadTime) : curr,
                    );
                }
            })
            .catch(() => {
                // Keep optimistic UI state on transient network error
            });
    };

    const handleAcknowledgePolicy = async (item: AnnouncementItem): Promise<void> => {
        if (item.category !== 'POLICY' || item.is_acknowledged || isAcknowledging) {
            return;
        }

        setIsAcknowledging(true);
        try {
            const response = await fetch(`/announcements/${item.id}/acknowledge`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': getXsrfToken(),
                },
            });

            if (response.ok) {
                const payload = (await response.json()) as {
                    data?: {
                        read_at_formatted?: string | null;
                        acknowledged_at_formatted?: string | null;
                    };
                };
                const nowFormatted = new Date().toISOString().slice(0, 16).replace('T', ' ');
                const ackTime = payload.data?.acknowledged_at_formatted ?? nowFormatted;

                const updateItemWithAck = (ann: AnnouncementItem): AnnouncementItem => {
                    const currentRecipients = ann.recipient_statuses ?? [];
                    const updatedRecipients = currentRecipients.map((r) =>
                        r.user_id === auth.user.id
                            ? {
                                  ...r,
                                  is_read: true,
                                  read_at: r.read_at ?? ackTime,
                                  is_acknowledged: true,
                                  acknowledged_at: ackTime,
                              }
                            : r,
                    );

                    return {
                        ...ann,
                        is_read: true,
                        read_at_formatted: ann.read_at_formatted ?? ackTime,
                        is_acknowledged: true,
                        acknowledged_at_formatted: ackTime,
                        acknowledged_count: (ann.acknowledged_count ?? 0) + 1,
                        recipient_statuses: updatedRecipients,
                    };
                };

                setAnnouncementList((prev) =>
                    prev.map((ann) => (ann.id === item.id ? updateItemWithAck(ann) : ann)),
                );
                setViewingAnnouncement((curr) =>
                    curr && curr.id === item.id ? updateItemWithAck(curr) : curr,
                );
            }
        } finally {
            setIsAcknowledging(false);
        }
    };

    const form = useForm<AnnouncementFormState>({
        title: '',
        category: 'INFO',
        content: '',
        is_pinned: false,
        is_active: true,
        expires_at: '',
        attachment: null,
    });

    const formErrors = form.errors as Record<string, string | undefined>;

    const openCreateModal = (): void => {
        setEditingItem(null);
        form.setData({
            title: '',
            category: 'INFO',
            content: '',
            is_pinned: false,
            is_active: true,
            expires_at: '',
            attachment: null,
        });
        form.clearErrors();
        setIsFormOpen(true);
    };

    const openEditModal = (item: AnnouncementItem): void => {
        setEditingItem(item);
        form.setData({
            title: item.title,
            category: item.category,
            content: item.content,
            is_pinned: item.is_pinned,
            is_active: item.is_active,
            expires_at: item.expires_date ?? '',
            attachment: null,
        });
        form.clearErrors();
        setIsFormOpen(true);
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        if (editingItem) {
            form.transform((data) => ({
                ...data,
                _method: 'PUT',
            }));
            form.post(`/announcements/${editingItem.id}`, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    setIsFormOpen(false);
                    setEditingItem(null);
                },
            });
            return;
        }

        form.transform((data) => data);
        form.post('/announcements', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setIsFormOpen(false);
            },
        });
    };

    const handleDelete = (item: AnnouncementItem): void => {
        if (!window.confirm(`Hapus pengumuman "${item.title}"?`)) {
            return;
        }

        router.delete(`/announcements/${item.id}`, {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Informasi & Pengumuman Perusahaan" />

            <div className="flex flex-1 flex-col gap-4 overflow-x-hidden p-3.5 sm:gap-6 sm:p-6">
                <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="flex items-center gap-2 text-lg font-semibold tracking-tight sm:text-2xl">
                            <Megaphone className="text-primary size-6 shrink-0" />
                            Informasi &amp; Pengumuman Perusahaan
                        </h1>
                        <p className="text-muted-foreground text-xs sm:text-sm">
                            Pusat berita internal, kebijakan HR terbaru, dan jadwal kegiatan perusahaan.
                        </p>
                    </div>

                    {canManageAnnouncements && (
                        <Button
                            type="button"
                            size="lg"
                            className="min-h-11 w-full sm:w-auto"
                            onClick={openCreateModal}
                        >
                            <Plus className="size-4" />
                            + Buat Pengumuman Baru
                        </Button>
                    )}
                </div>

                {flash?.success && (
                    <Alert className="border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-300">
                        <CheckCircle2 className="size-4 text-emerald-600 dark:text-emerald-400" />
                        <AlertTitle>Berhasil</AlertTitle>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}

                <Card>
                    <CardHeader className="p-3.5 sm:p-6">
                        <CardTitle className="text-base sm:text-lg">Daftar Pengumuman</CardTitle>
                        <CardDescription className="text-xs sm:text-sm">
                            Pengumuman yang disematkan (Pin) akan selalu muncul di urutan paling atas pada Beranda.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-3.5 pt-0 sm:p-6 sm:pt-0">
                        {announcementList.length === 0 ? (
                            <div className="text-muted-foreground rounded-lg border border-dashed p-8 text-center text-sm">
                                Belum ada pengumuman perusahaan yang tercatat.
                            </div>
                        ) : (
                            <div className="grid grid-cols-1 gap-3.5 md:grid-cols-2">
                                {announcementList.map((item) => {
                                    const isUnread = item.is_read === false;
                                    const needsAcknowledge =
                                        item.category === 'POLICY' && item.is_acknowledged === false;
                                    const totalRecipients = item.total_recipients ?? 0;
                                    const readCount = item.read_count ?? 0;
                                    const acknowledgedCount = item.acknowledged_count ?? 0;

                                    return (
                                        <div
                                            key={item.id}
                                            className={`flex flex-col justify-between gap-3 rounded-xl border p-4 transition-all ${
                                                item.is_pinned ? 'border-amber-500/40 bg-amber-500/[0.03]' : ''
                                            } ${!item.is_active ? 'opacity-65' : ''} ${
                                                isUnread ? 'ring-primary/20 border-primary/30 ring-1' : ''
                                            }`}
                                        >
                                            <div className="space-y-2">
                                                <div className="flex flex-wrap items-center justify-between gap-2">
                                                    <div className="flex flex-wrap items-center gap-1.5">
                                                        <Badge
                                                            variant="outline"
                                                            className={getAnnouncementCategoryBadgeClass(item.category)}
                                                        >
                                                            {item.category_label}
                                                        </Badge>
                                                        {item.is_pinned && (
                                                            <Badge
                                                                variant="outline"
                                                                className="gap-1 border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300"
                                                            >
                                                                <Pin className="size-3" />
                                                                Disematkan
                                                            </Badge>
                                                        )}
                                                        {!item.is_active && (
                                                            <Badge variant="secondary">Diarsipkan</Badge>
                                                        )}
                                                        {isUnread && (
                                                            <Badge
                                                                variant="default"
                                                                className="gap-1 bg-blue-600 text-white hover:bg-blue-600"
                                                            >
                                                                <Sparkles className="size-3" />
                                                                Baru / Belum Dibaca
                                                            </Badge>
                                                        )}
                                                        {needsAcknowledge && (
                                                            <Badge
                                                                variant="outline"
                                                                className="gap-1 border-rose-500/50 bg-rose-500/10 font-semibold text-rose-700 dark:text-rose-300"
                                                            >
                                                                <ShieldAlert className="size-3" />
                                                                Wajib Konfirmasi
                                                            </Badge>
                                                        )}
                                                        {item.category === 'POLICY' && item.is_acknowledged && (
                                                            <Badge
                                                                variant="outline"
                                                                className="gap-1 border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                                                            >
                                                                <CheckCircle2 className="size-3" />
                                                                Sudah Dikonfirmasi
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    <span className="text-muted-foreground font-mono text-xs">
                                                        {item.published_date ?? '-'}
                                                    </span>
                                                </div>

                                                <h3 className="text-base font-semibold">{item.title}</h3>
                                                <p className="text-muted-foreground line-clamp-3 text-xs leading-relaxed sm:text-sm">
                                                    {item.content}
                                                </p>

                                                {canManageAnnouncements && (
                                                    <div className="pt-1">
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                setTrackingFilter('ALL');
                                                                setTrackingAnnouncement(item);
                                                            }}
                                                            className="hover:bg-muted/60 inline-flex flex-wrap items-center gap-2 rounded-lg border px-2.5 py-1.5 text-left text-xs transition-colors"
                                                        >
                                                            <Users className="text-primary size-3.5 shrink-0" />
                                                            <span>
                                                                Status Baca:{' '}
                                                                <strong>
                                                                    {readCount} / {totalRecipients} Karyawan
                                                                </strong>
                                                            </span>
                                                            {item.category === 'POLICY' && (
                                                                <span className="border-l pl-2 text-indigo-700 dark:text-indigo-300">
                                                                    Konfirmasi:{' '}
                                                                    <strong>
                                                                        {acknowledgedCount} / {totalRecipients}
                                                                    </strong>
                                                                </span>
                                                            )}
                                                        </button>
                                                    </div>
                                                )}
                                            </div>

                                            <div className="flex flex-wrap items-center justify-between gap-2 border-t pt-3 text-xs">
                                                <span className="text-muted-foreground">
                                                    Oleh: <strong>{item.author_name}</strong>
                                                    {item.expires_date && ` • Berlaku s/d ${item.expires_date}`}
                                                </span>

                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        className="min-h-9"
                                                        onClick={() => handleOpenDetailModal(item)}
                                                    >
                                                        <Eye className="size-3.5" />
                                                        Baca
                                                    </Button>

                                                    {canManageAnnouncements && (
                                                        <>
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                size="sm"
                                                                className="min-h-9"
                                                                onClick={() => openEditModal(item)}
                                                            >
                                                                <Edit3 className="size-3.5" />
                                                                Edit
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="sm"
                                                                className="text-destructive hover:text-destructive min-h-9"
                                                                onClick={() => handleDelete(item)}
                                                            >
                                                                <Trash2 className="size-3.5" />
                                                            </Button>
                                                        </>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Modal Baca Detail Pengumuman */}
            <Dialog
                open={viewingAnnouncement !== null}
                onOpenChange={(open) => !open && setViewingAnnouncement(null)}
            >
                <DialogContent className="max-h-[85vh] w-[95vw] max-w-full overflow-y-auto p-3.5 sm:max-w-xl sm:p-6">
                    {viewingAnnouncement && (
                        <div className="flex flex-col gap-4">
                            <DialogHeader className="sticky top-0 z-10 border-b bg-white pb-2.5 dark:bg-slate-950">
                                <div className="mb-1.5 flex flex-wrap items-center gap-1.5 pr-6">
                                    <Badge
                                        variant="outline"
                                        className={getAnnouncementCategoryBadgeClass(viewingAnnouncement.category)}
                                    >
                                        {viewingAnnouncement.category_label}
                                    </Badge>
                                    {viewingAnnouncement.is_pinned && (
                                        <Badge
                                            variant="outline"
                                            className="gap-1 border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300"
                                        >
                                            <Pin className="size-3" />
                                            Disematkan
                                        </Badge>
                                    )}
                                </div>
                                <DialogTitle className="text-base sm:text-lg">
                                    {viewingAnnouncement.title}
                                </DialogTitle>
                                <DialogDescription className="text-xs">
                                    Diterbitkan tanggal {viewingAnnouncement.published_date ?? '-'} oleh{' '}
                                    <strong>{viewingAnnouncement.author_name}</strong>
                                </DialogDescription>
                            </DialogHeader>

                            <div className="bg-muted/20 rounded-lg border p-3.5 text-sm leading-relaxed whitespace-pre-line">
                                {viewingAnnouncement.content}
                            </div>

                            {viewingAnnouncement.category === 'POLICY' && (
                                <div className="rounded-lg border border-indigo-500/30 bg-indigo-500/[0.04] p-3.5">
                                    {viewingAnnouncement.is_acknowledged ? (
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge
                                                variant="outline"
                                                className="gap-1.5 border-emerald-500/40 bg-emerald-500/10 px-3 py-1 text-xs font-medium text-emerald-700 dark:text-emerald-300"
                                            >
                                                <CheckCircle2 className="size-3.5 shrink-0" />
                                                Sudah Dikonfirmasi pada{' '}
                                                {viewingAnnouncement.acknowledged_at_formatted ?? 'Hari ini'}
                                            </Badge>
                                        </div>
                                    ) : (
                                        <div className="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between">
                                            <div className="space-y-0.5 text-xs">
                                                <p className="font-semibold text-indigo-950 dark:text-indigo-200">
                                                    Konfirmasi Persetujuan Kebijakan HR
                                                </p>
                                                <p className="text-muted-foreground">
                                                    Mohon tekan tombol konfirmasi di bawah sebagai bukti Anda telah membaca dan memahami kebijakan ini.
                                                </p>
                                            </div>
                                            <Button
                                                type="button"
                                                size="sm"
                                                disabled={isAcknowledging}
                                                onClick={() => void handleAcknowledgePolicy(viewingAnnouncement)}
                                                className="min-h-10 w-full shrink-0 bg-emerald-600 text-white hover:bg-emerald-700 sm:w-auto"
                                            >
                                                <FileCheck2 className="size-4" />
                                                {isAcknowledging
                                                    ? 'Mengonfirmasi...'
                                                    : 'Saya Sudah Membaca & Memahami'}
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            )}

                            <DialogFooter className="sticky bottom-0 z-10 flex flex-col gap-2 border-t bg-white pt-2.5 sm:flex-row sm:justify-between dark:bg-slate-950">
                                {viewingAnnouncement.attachment_url ? (
                                    <Button asChild variant="outline" size="sm" className="min-h-9 w-full sm:w-auto">
                                        <a
                                            href={viewingAnnouncement.attachment_url}
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
                                    onClick={() => setViewingAnnouncement(null)}
                                >
                                    Tutup
                                </Button>
                            </DialogFooter>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            {/* Modal Daftar Status Baca & Konfirmasi Karyawan (HR Admin / Superadmin) */}
            <Dialog
                open={trackingAnnouncement !== null}
                onOpenChange={(open) => !open && setTrackingAnnouncement(null)}
            >
                <DialogContent className="max-h-[88vh] w-[95vw] max-w-full overflow-y-auto p-3.5 sm:max-w-2xl sm:p-6">
                    {trackingAnnouncement && (
                        <div className="flex flex-col gap-4">
                            <DialogHeader className="sticky top-0 z-10 border-b bg-white pb-2.5 dark:bg-slate-950">
                                <DialogTitle className="flex items-center gap-2 text-base sm:text-lg">
                                    <Users className="text-primary size-5 shrink-0" />
                                    Status Baca &amp; Konfirmasi Karyawan
                                </DialogTitle>
                                <DialogDescription className="text-xs sm:text-sm">
                                    {trackingAnnouncement.title} ({trackingAnnouncement.category_label})
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid grid-cols-3 gap-2 text-center text-xs">
                                <button
                                    type="button"
                                    onClick={() => setTrackingFilter('ALL')}
                                    className={`rounded-lg border p-2.5 transition-colors ${
                                        trackingFilter === 'ALL'
                                            ? 'border-primary bg-primary/10 font-semibold'
                                            : 'hover:bg-muted/40'
                                    }`}
                                >
                                    <div className="text-base font-bold">
                                        {trackingAnnouncement.total_recipients ?? 0}
                                    </div>
                                    <div className="text-muted-foreground">Total Karyawan</div>
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setTrackingFilter('ACKNOWLEDGED')}
                                    className={`rounded-lg border p-2.5 transition-colors ${
                                        trackingFilter === 'ACKNOWLEDGED'
                                            ? 'border-emerald-600 bg-emerald-500/10 font-semibold'
                                            : 'hover:bg-muted/40'
                                    }`}
                                >
                                    <div className="text-base font-bold text-emerald-600 dark:text-emerald-400">
                                        {trackingAnnouncement.category === 'POLICY'
                                            ? (trackingAnnouncement.acknowledged_count ?? 0)
                                            : (trackingAnnouncement.read_count ?? 0)}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {trackingAnnouncement.category === 'POLICY'
                                            ? 'Sudah Konfirmasi'
                                            : 'Sudah Membaca'}
                                    </div>
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setTrackingFilter('UNREAD')}
                                    className={`rounded-lg border p-2.5 transition-colors ${
                                        trackingFilter === 'UNREAD'
                                            ? 'border-rose-600 bg-rose-500/10 font-semibold'
                                            : 'hover:bg-muted/40'
                                    }`}
                                >
                                    <div className="text-base font-bold text-rose-600 dark:text-rose-400">
                                        {Math.max(
                                            0,
                                            (trackingAnnouncement.total_recipients ?? 0) -
                                                (trackingAnnouncement.category === 'POLICY'
                                                    ? (trackingAnnouncement.acknowledged_count ?? 0)
                                                    : (trackingAnnouncement.read_count ?? 0)),
                                        )}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {trackingAnnouncement.category === 'POLICY'
                                            ? 'Belum Konfirmasi'
                                            : 'Belum Membaca'}
                                    </div>
                                </button>
                            </div>

                            <div className="max-h-[48vh] overflow-y-auto rounded-lg border">
                                <table className="w-full text-left text-xs sm:text-sm">
                                    <thead className="bg-muted/50 sticky top-0 border-b text-[11px] uppercase">
                                        <tr>
                                            <th className="px-3 py-2.5">Karyawan</th>
                                            <th className="px-3 py-2.5">Status Baca</th>
                                            {trackingAnnouncement.category === 'POLICY' && (
                                                <th className="px-3 py-2.5">Konfirmasi Kebijakan</th>
                                            )}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {(trackingAnnouncement.recipient_statuses ?? [])
                                            .filter((recipient) => {
                                                if (trackingFilter === 'ACKNOWLEDGED') {
                                                    return trackingAnnouncement.category === 'POLICY'
                                                        ? recipient.is_acknowledged
                                                        : recipient.is_read;
                                                }
                                                if (trackingFilter === 'UNREAD') {
                                                    return trackingAnnouncement.category === 'POLICY'
                                                        ? !recipient.is_acknowledged
                                                        : !recipient.is_read;
                                                }
                                                return true;
                                            })
                                            .map((recipient) => (
                                                <tr key={recipient.user_id} className="hover:bg-muted/20">
                                                    <td className="px-3 py-2.5">
                                                        <div className="font-medium">{recipient.full_name}</div>
                                                        <div className="text-muted-foreground text-[11px]">
                                                            {recipient.employee_code} • {recipient.department_name}
                                                        </div>
                                                    </td>
                                                    <td className="px-3 py-2.5">
                                                        {recipient.is_read ? (
                                                            <div className="space-y-0.5">
                                                                <Badge
                                                                    variant="outline"
                                                                    className="gap-1 border-emerald-500/40 bg-emerald-500/10 text-[11px] text-emerald-700 dark:text-emerald-300"
                                                                >
                                                                    <CheckCircle2 className="size-3" />
                                                                    Sudah Dibaca
                                                                </Badge>
                                                                {recipient.read_at && (
                                                                    <div className="text-muted-foreground font-mono text-[10px]">
                                                                        {recipient.read_at}
                                                                    </div>
                                                                )}
                                                            </div>
                                                        ) : (
                                                            <Badge
                                                                variant="outline"
                                                                className="text-muted-foreground gap-1 text-[11px]"
                                                            >
                                                                <Clock className="size-3" />
                                                                Belum Dibaca
                                                            </Badge>
                                                        )}
                                                    </td>
                                                    {trackingAnnouncement.category === 'POLICY' && (
                                                        <td className="px-3 py-2.5">
                                                            {recipient.is_acknowledged ? (
                                                                <div className="space-y-0.5">
                                                                    <Badge
                                                                        variant="outline"
                                                                        className="gap-1 border-emerald-500/40 bg-emerald-500/10 text-[11px] text-emerald-700 dark:text-emerald-300"
                                                                    >
                                                                        <CheckCircle2 className="size-3" />
                                                                        Dikonfirmasi
                                                                    </Badge>
                                                                    {recipient.acknowledged_at && (
                                                                        <div className="text-muted-foreground font-mono text-[10px]">
                                                                            {recipient.acknowledged_at}
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            ) : (
                                                                <Badge
                                                                    variant="outline"
                                                                    className="gap-1 border-rose-500/40 bg-rose-500/10 text-[11px] text-rose-700 dark:text-rose-300"
                                                                >
                                                                    <ShieldAlert className="size-3" />
                                                                    Belum Konfirmasi
                                                                </Badge>
                                                            )}
                                                        </td>
                                                    )}
                                                </tr>
                                            ))}
                                    </tbody>
                                </table>
                            </div>

                            <DialogFooter className="sticky bottom-0 z-10 border-t bg-white pt-2.5 dark:bg-slate-950">
                                <Button
                                    type="button"
                                    size="sm"
                                    className="min-h-9 w-full sm:w-auto"
                                    onClick={() => setTrackingAnnouncement(null)}
                                >
                                    Tutup
                                </Button>
                            </DialogFooter>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            {/* Modal Tambah & Edit Pengumuman (HR Admin / Superadmin) */}
            <Dialog open={isFormOpen} onOpenChange={setIsFormOpen}>
                <DialogContent className="max-h-[88vh] w-[95vw] max-w-full overflow-y-auto p-3.5 sm:max-w-xl sm:p-6">
                    <form onSubmit={handleSubmit} className="flex flex-col gap-3.5 sm:gap-4">
                        <DialogHeader className="sticky top-0 z-10 border-b bg-white pb-2.5 dark:bg-slate-950">
                            <DialogTitle className="text-base sm:text-lg">
                                {editingItem ? 'Edit Pengumuman Perusahaan' : 'Buat Pengumuman Perusahaan Baru'}
                            </DialogTitle>
                            <DialogDescription className="text-xs sm:text-sm">
                                Pengumuman yang aktif akan langsung tampil di halaman Beranda seluruh karyawan.
                            </DialogDescription>
                        </DialogHeader>

                        {formErrors.title && (
                            <Alert variant="destructive">
                                <AlertCircle className="size-4" />
                                <AlertTitle>Validasi Gagal</AlertTitle>
                                <AlertDescription>{formErrors.title}</AlertDescription>
                            </Alert>
                        )}

                        <div className="grid gap-1.5">
                            <Label htmlFor="ann_title" className="text-xs sm:text-sm">
                                Judul Informasi / Berita
                            </Label>
                            <Input
                                id="ann_title"
                                value={form.data.title}
                                onChange={(e) => form.setData('title', e.target.value)}
                                placeholder="Contoh: Pengumuman Libur Nasional & Cuti Bersama"
                                className="w-full max-w-full text-sm sm:text-base"
                                required
                            />
                        </div>

                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor="ann_category" className="text-xs sm:text-sm">
                                    Kategori Pengumuman
                                </Label>
                                <select
                                    id="ann_category"
                                    value={form.data.category}
                                    onChange={(e) =>
                                        form.setData('category', e.target.value as AnnouncementCategoryValue)
                                    }
                                    className="border-input bg-background flex h-9 w-full max-w-full rounded-md border px-3 py-1 text-sm shadow-xs outline-none sm:text-base"
                                >
                                    {CATEGORIES.map((cat) => (
                                        <option key={cat.value} value={cat.value}>
                                            {cat.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="ann_expires" className="text-xs sm:text-sm">
                                    Tanggal Kedaluwarsa (Opsional)
                                </Label>
                                <Input
                                    id="ann_expires"
                                    type="date"
                                    value={form.data.expires_at}
                                    onChange={(e) => form.setData('expires_at', e.target.value)}
                                    className="w-full max-w-full text-sm sm:text-base"
                                />
                            </div>
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="ann_content" className="text-xs sm:text-sm">
                                Isi Pengumuman Lengkap
                            </Label>
                            <textarea
                                id="ann_content"
                                rows={5}
                                value={form.data.content}
                                onChange={(e) => form.setData('content', e.target.value)}
                                placeholder="Tuliskan detail pengumuman atau kebijakan..."
                                required
                                className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 flex w-full max-w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] sm:text-base"
                            />
                            {formErrors.content && (
                                <p className="text-destructive text-xs">{formErrors.content}</p>
                            )}
                        </div>

                        <div className="grid grid-cols-1 gap-2.5 rounded-lg border p-3 sm:grid-cols-2">
                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="ann_pinned"
                                    checked={form.data.is_pinned}
                                    onCheckedChange={(checked) => form.setData('is_pinned', Boolean(checked))}
                                />
                                <Label htmlFor="ann_pinned" className="text-xs font-medium sm:text-sm">
                                    Sematkan di Atas (Pin)
                                </Label>
                            </div>

                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="ann_active"
                                    checked={form.data.is_active}
                                    onCheckedChange={(checked) => form.setData('is_active', Boolean(checked))}
                                />
                                <Label htmlFor="ann_active" className="text-xs font-medium sm:text-sm">
                                    Status Aktif / Tampilkan
                                </Label>
                            </div>
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="ann_attachment" className="text-xs sm:text-sm">
                                Lampiran Dokumen / Gambar (Opsional — PDF/JPG/PNG, maks 5MB)
                            </Label>
                            <Input
                                id="ann_attachment"
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png"
                                className="w-full max-w-full text-sm sm:text-base"
                                onChange={(e) => {
                                    const file = e.target.files?.[0] ?? null;
                                    form.setData('attachment', file);
                                }}
                            />
                            {formErrors.attachment && (
                                <p className="text-destructive text-xs">{formErrors.attachment}</p>
                            )}
                        </div>

                        <DialogFooter className="sticky bottom-0 z-10 flex flex-col gap-2 border-t bg-white pt-2.5 sm:flex-row dark:bg-slate-950">
                            <Button
                                type="button"
                                variant="outline"
                                className="min-h-10 w-full sm:w-auto"
                                onClick={() => setIsFormOpen(false)}
                                disabled={form.processing}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                className="min-h-10 w-full sm:w-auto"
                                disabled={form.processing}
                            >
                                {form.processing
                                    ? 'Menyimpan...'
                                    : editingItem
                                      ? 'Simpan Perubahan'
                                      : 'Terbitkan Pengumuman'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

