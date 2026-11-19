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
} from '@/pages/dashboard';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCircle2,
    Download,
    Edit3,
    Eye,
    Megaphone,
    Pin,
    Plus,
    Trash2,
} from 'lucide-react';
import { type FormEvent, useState } from 'react';

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
    const { flash } = usePage<SharedData>().props;

    const [viewingAnnouncement, setViewingAnnouncement] = useState<AnnouncementItem | null>(null);
    const [isFormOpen, setIsFormOpen] = useState<boolean>(false);
    const [editingItem, setEditingItem] = useState<AnnouncementItem | null>(null);

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
                        {announcements.length === 0 ? (
                            <div className="text-muted-foreground rounded-lg border border-dashed p-8 text-center text-sm">
                                Belum ada pengumuman perusahaan yang tercatat.
                            </div>
                        ) : (
                            <div className="grid grid-cols-1 gap-3.5 md:grid-cols-2">
                                {announcements.map((item) => (
                                    <div
                                        key={item.id}
                                        className={`flex flex-col justify-between gap-3 rounded-xl border p-4 transition-all ${
                                            item.is_pinned ? 'border-amber-500/40 bg-amber-500/[0.03]' : ''
                                        } ${!item.is_active ? 'opacity-65' : ''}`}
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
                                                </div>
                                                <span className="text-muted-foreground font-mono text-xs">
                                                    {item.published_date ?? '-'}
                                                </span>
                                            </div>

                                            <h3 className="text-base font-semibold">{item.title}</h3>
                                            <p className="text-muted-foreground line-clamp-3 text-xs leading-relaxed sm:text-sm">
                                                {item.content}
                                            </p>
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
                                                    onClick={() => setViewingAnnouncement(item)}
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
                                ))}
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

