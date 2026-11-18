import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import {
    type AttendanceProps,
    type AttendanceStatusValue,
    type BreadcrumbItem,
    type SharedData,
} from '@/types';
import { Capacitor } from '@capacitor/core';
import { Head, router, usePage } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, Clock, Info, LogIn, LogOut, MapPin, Timer } from 'lucide-react';
import { useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Daily Attendance',
        href: '/attendance',
    },
];

function formatTime(isoString: string | null): string {
    if (!isoString) {
        return '-';
    }

    const parsed = new Date(isoString);
    if (Number.isNaN(parsed.getTime())) {
        return isoString;
    }

    return parsed.toLocaleTimeString('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

function formatDate(dateStr: string | null): string {
    if (!dateStr) {
        return '-';
    }

    const parsed = new Date(`${dateStr}T00:00:00`);
    if (Number.isNaN(parsed.getTime())) {
        return dateStr;
    }

    return parsed.toLocaleDateString('en-GB', {
        weekday: 'short',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function formatDuration(minutes: number): string {
    if (!minutes || minutes <= 0) {
        return '-';
    }

    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;

    return `${hours}h ${remainingMinutes}m`;
}

function getAttendanceBadgeClass(status: AttendanceStatusValue): string {
    switch (status) {
        case 'PRESENT':
            return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400';
        case 'LATE':
            return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-400';
        case 'ABSENT':
            return 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-400';
        case 'ON_LEAVE':
        case 'SICK':
            return 'border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-400';
        case 'HOLIDAY':
            return 'border-violet-500/30 bg-violet-500/10 text-violet-700 dark:text-violet-400';
    }
}

interface LocationResolutionResult {
    coords: string | null;
    fallbackMessage: string | null;
}

async function resolveBrowserLocation(): Promise<LocationResolutionResult> {
    if (typeof window === 'undefined') {
        return { coords: null, fallbackMessage: null };
    }

    // Native Android / iOS Capacitor Runtime: use hardware GPS plugin directly
    if (Capacitor.isNativePlatform()) {
        try {
            const { Geolocation } = await import('@capacitor/geolocation');
            await Geolocation.requestPermissions();
            const position = await Geolocation.getCurrentPosition({
                enableHighAccuracy: true,
                timeout: 8000,
                maximumAge: 30000,
            });
            const lat = position.coords.latitude.toFixed(6);
            const lng = position.coords.longitude.toFixed(6);

            return { coords: `${lat},${lng}`, fallbackMessage: null };
        } catch {
            return {
                coords: null,
                fallbackMessage:
                    'Izin lokasi GPS Android belum diaktifkan; presensi tetap dicatat menggunakan verifikasi alamat IP.',
            };
        }
    }

    if (!window.isSecureContext && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
        return {
            coords: null,
            fallbackMessage:
                'Koneksi HTTP Jaringan Lokal terdeteksi: Browser HP menonaktifkan GPS pada non-HTTPS, sehingga presensi dicatat otomatis menggunakan verifikasi IP Wi-Fi/LAN.',
        };
    }

    if (!('geolocation' in navigator)) {
        return {
            coords: null,
            fallbackMessage: 'Perangkat tidak menyediakan sensor GPS; presensi menggunakan verifikasi IP jaringan.',
        };
    }

    return new Promise((resolve) => {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude.toFixed(6);
                const lng = position.coords.longitude.toFixed(6);
                resolve({ coords: `${lat},${lng}`, fallbackMessage: null });
            },
            () =>
                resolve({
                    coords: null,
                    fallbackMessage:
                        'Akses lokasi GPS tidak tersedia/ditolak di browser; presensi tetap dicatat menggunakan verifikasi alamat IP.',
                }),
            {
                enableHighAccuracy: false,
                timeout: 2500,
                maximumAge: 60000,
            },
        );
    });
}

export default function AttendanceIndex({
    employee,
    todayAttendance,
    monthlyRecords,
    currentMonthLabel,
    serverTime,
}: AttendanceProps) {
    const page = usePage<SharedData & { errors?: Record<string, string> }>();
    const flash = page.props.flash;
    const errors = page.props.errors ?? {};

    const [now, setNow] = useState<Date>(() => {
        const initial = new Date(serverTime);
        return Number.isNaN(initial.getTime()) ? new Date() : initial;
    });
    const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
    const [detectedCoords, setDetectedCoords] = useState<string | null>(null);
    const [geoFallbackNotice, setGeoFallbackNotice] = useState<string | null>(() => {
        if (
            typeof window !== 'undefined' &&
            !Capacitor.isNativePlatform() &&
            !window.isSecureContext &&
            window.location.hostname !== 'localhost' &&
            window.location.hostname !== '127.0.0.1'
        ) {
            return 'Mode Jaringan Lokal (HTTP): Browser HP membatasi GPS pada koneksi non-HTTPS. Clock-In & Clock-Out tetap diizinkan menggunakan verifikasi IP jaringan.';
        }
        return null;
    });

    useEffect(() => {
        const intervalId = window.setInterval(() => {
            setNow((prev) => new Date(prev.getTime() + 1000));
        }, 1000);

        return () => window.clearInterval(intervalId);
    }, []);

    const hasClockedIn = Boolean(todayAttendance?.clock_in);
    const hasClockedOut = Boolean(todayAttendance?.clock_out);

    const handleClockAction = async (action: 'clock-in' | 'clock-out'): Promise<void> => {
        setIsSubmitting(true);
        const { coords, fallbackMessage } = await resolveBrowserLocation();
        if (coords) {
            setDetectedCoords(coords);
            setGeoFallbackNotice(null);
        } else if (fallbackMessage) {
            setGeoFallbackNotice(fallbackMessage);
        }

        const endpoint = action === 'clock-in' ? '/attendance/clock-in' : '/attendance/clock-out';

        router.post(
            endpoint,
            {
                location: coords,
            },
            {
                preserveScroll: true,
                onFinish: () => setIsSubmitting(false),
            },
        );
    };

    const errorMessage = errors.attendance ?? errors.employee_id ?? errors.location ?? null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Daily Attendance" />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:gap-6 sm:p-6">
                <div className="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight sm:text-2xl">Daily Attendance</h1>
                        <p className="text-muted-foreground text-sm">
                            Record your daily clock-in and clock-out times and monitor {currentMonthLabel} attendance history.
                        </p>
                    </div>
                    {employee && (
                        <div className="text-sm sm:text-right">
                            <p className="font-medium">{employee.full_name}</p>
                            <p className="text-muted-foreground text-xs">
                                {employee.employee_code}
                                {employee.position_title ? ` • ${employee.position_title}` : ''}
                            </p>
                            <p className="text-muted-foreground text-xs">
                                {employee.phone_number ? `${employee.phone_number} • ` : ''}
                                {employee.marital_status ?? 'SINGLE'} ({employee.dependents_count ?? 0} dependents)
                            </p>
                        </div>
                    )}
                </div>

                {flash?.success && (
                    <Alert className="border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-300">
                        <CheckCircle2 className="size-4 text-emerald-600 dark:text-emerald-400" />
                        <AlertTitle>Success</AlertTitle>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}

                {geoFallbackNotice && (
                    <Alert className="border-sky-500/40 bg-sky-500/10 text-sky-900 dark:text-sky-200">
                        <Info className="size-4 text-sky-600 dark:text-sky-400" />
                        <AlertTitle>Verifikasi Berbasis IP Aktif</AlertTitle>
                        <AlertDescription>{geoFallbackNotice}</AlertDescription>
                    </Alert>
                )}

                {(errorMessage || flash?.error) && (
                    <Alert variant="destructive">
                        <AlertCircle className="size-4" />
                        <AlertTitle>Attendance Action Failed</AlertTitle>
                        <AlertDescription>{errorMessage ?? flash?.error}</AlertDescription>
                    </Alert>
                )}

                {!employee ? (
                    <Alert variant="destructive">
                        <AlertCircle className="size-4" />
                        <AlertTitle>No Linked Employee Profile</AlertTitle>
                        <AlertDescription>
                            Your user account is not linked to an active employee record yet. Please contact HR Administration.
                        </AlertDescription>
                    </Alert>
                ) : (
                    <div className="grid gap-5 lg:grid-cols-12 lg:gap-6">
                        <Card className="lg:col-span-5">
                            <CardHeader>
                                <div className="flex items-center justify-between gap-2">
                                    <CardTitle className="flex items-center gap-2">
                                        <Clock className="text-primary size-5" />
                                        Live Clock Terminal
                                    </CardTitle>
                                    {todayAttendance ? (
                                        <Badge variant="outline" className={getAttendanceBadgeClass(todayAttendance.status)}>
                                            {todayAttendance.status}
                                        </Badge>
                                    ) : (
                                        <Badge variant="secondary">NOT CLOCKED IN</Badge>
                                    )}
                                </div>
                                <CardDescription>
                                    {employee.shift
                                        ? `Shift: ${employee.shift.name} (${employee.shift.start_time.slice(0, 5)} - ${employee.shift.end_time.slice(0, 5)})`
                                        : 'No shift assigned — please contact HR before clocking in.'}
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="flex flex-col gap-5">
                                <div className="bg-muted/40 flex flex-col items-center justify-center rounded-xl border py-6 text-center">
                                    <div className="font-mono text-3xl font-bold tracking-wider tabular-nums sm:text-4xl">
                                        {now.toLocaleTimeString('en-GB', {
                                            hour: '2-digit',
                                            minute: '2-digit',
                                            second: '2-digit',
                                        })}
                                    </div>
                                    <p className="text-muted-foreground mt-1.5 text-sm">
                                        {now.toLocaleDateString('en-GB', {
                                            weekday: 'long',
                                            day: 'numeric',
                                            month: 'long',
                                            year: 'numeric',
                                        })}
                                    </p>
                                    {(detectedCoords || todayAttendance?.clock_in_location) && (
                                        <p className="text-muted-foreground mt-2 inline-flex items-center gap-1 text-xs">
                                            <MapPin className="size-3.5" />
                                            Location: {detectedCoords ?? todayAttendance?.clock_in_location}
                                        </p>
                                    )}
                                </div>

                                <div className="grid grid-cols-2 gap-3 text-sm">
                                    <div className="rounded-lg border p-3">
                                        <p className="text-muted-foreground text-xs">Clock In</p>
                                        <p className="mt-1 font-mono font-semibold">
                                            {formatTime(todayAttendance?.clock_in ?? null)}
                                        </p>
                                    </div>
                                    <div className="rounded-lg border p-3">
                                        <p className="text-muted-foreground text-xs">Clock Out</p>
                                        <p className="mt-1 font-mono font-semibold">
                                            {formatTime(todayAttendance?.clock_out ?? null)}
                                        </p>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <Button
                                        type="button"
                                        size="lg"
                                        disabled={isSubmitting || hasClockedIn || !employee.shift}
                                        onClick={() => void handleClockAction('clock-in')}
                                        className="min-h-12 w-full text-base font-semibold"
                                    >
                                        <LogIn className="size-5" />
                                        {isSubmitting && !hasClockedIn ? 'Recording...' : 'Clock In'}
                                    </Button>

                                    <Button
                                        type="button"
                                        size="lg"
                                        variant={hasClockedIn && !hasClockedOut ? 'default' : 'outline'}
                                        disabled={isSubmitting || !hasClockedIn || hasClockedOut}
                                        onClick={() => void handleClockAction('clock-out')}
                                        className="min-h-12 w-full text-base font-semibold"
                                    >
                                        <LogOut className="size-5" />
                                        {isSubmitting && hasClockedIn && !hasClockedOut ? 'Recording...' : 'Clock Out'}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="lg:col-span-7">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Timer className="text-primary size-5" />
                                    Today&apos;s Attendance Summary
                                </CardTitle>
                                <CardDescription>
                                    Real-time calculation of punctuality, work duration, and IP audit metadata.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid gap-4 sm:grid-cols-3">
                                    <div className="rounded-lg border p-4">
                                        <p className="text-muted-foreground text-xs uppercase">Late Duration</p>
                                        <p className="mt-2 text-2xl font-semibold">
                                            {todayAttendance ? `${todayAttendance.late_minutes} min` : '0 min'}
                                        </p>
                                        <p className="text-muted-foreground mt-1 text-xs">
                                            Grace: {employee.shift?.late_grace_period_minutes ?? 0} min
                                        </p>
                                    </div>

                                    <div className="rounded-lg border p-4">
                                        <p className="text-muted-foreground text-xs uppercase">Work Duration</p>
                                        <p className="mt-2 text-2xl font-semibold">
                                            {todayAttendance ? formatDuration(todayAttendance.work_duration_minutes) : '-'}
                                        </p>
                                        <p className="text-muted-foreground mt-1 text-xs">
                                            {hasClockedOut ? 'Completed for today' : hasClockedIn ? 'In progress' : 'Awaiting clock-in'}
                                        </p>
                                    </div>

                                    <div className="rounded-lg border p-4">
                                        <p className="text-muted-foreground text-xs uppercase">Recorded IP</p>
                                        <p className="mt-2 truncate font-mono text-sm font-semibold">
                                            {todayAttendance?.clock_in_ip ?? '-'}
                                        </p>
                                        <p className="text-muted-foreground mt-1 text-xs">
                                            Out IP: {todayAttendance?.clock_out_ip ?? '-'}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Attendance History — {currentMonthLabel}</CardTitle>
                        <CardDescription>
                            Complete daily attendance records for the current calendar month.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto border-t">
                            <table className="w-full min-w-[540px] text-left text-sm">
                                <thead className="bg-muted/50 border-b text-xs uppercase">
                                    <tr>
                                        <th className="px-4 py-3">Date</th>
                                        <th className="px-4 py-3">Clock In</th>
                                        <th className="px-4 py-3">Clock Out</th>
                                        <th className="px-4 py-3">Work Duration</th>
                                        <th className="px-4 py-3">Late</th>
                                        <th className="px-4 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {monthlyRecords.length === 0 ? (
                                        <tr>
                                            <td colSpan={6} className="text-muted-foreground px-4 py-8 text-center">
                                                No attendance records recorded for {currentMonthLabel}.
                                            </td>
                                        </tr>
                                    ) : (
                                        monthlyRecords.map((record) => (
                                            <tr key={record.id} className="hover:bg-muted/30 transition-colors">
                                                <td className="px-4 py-3 font-medium whitespace-nowrap">{formatDate(record.date)}</td>
                                                <td className="px-4 py-3 font-mono text-xs whitespace-nowrap">{formatTime(record.clock_in)}</td>
                                                <td className="px-4 py-3 font-mono text-xs whitespace-nowrap">{formatTime(record.clock_out)}</td>
                                                <td className="px-4 py-3 whitespace-nowrap">{formatDuration(record.work_duration_minutes)}</td>
                                                <td className="px-4 py-3 whitespace-nowrap">
                                                    {record.late_minutes > 0 ? (
                                                        <span className="font-medium text-amber-600 dark:text-amber-400">
                                                            {record.late_minutes} min
                                                        </span>
                                                    ) : (
                                                        <span className="text-muted-foreground">0 min</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap">
                                                    <Badge variant="outline" className={getAttendanceBadgeClass(record.status)}>
                                                        {record.status}
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
