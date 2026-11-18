import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { CalendarDays, Clock, LayoutGrid, ReceiptText } from 'lucide-react';

const mobileBottomItems = [
    {
        title: 'Home',
        url: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Presensi',
        url: '/attendance',
        icon: Clock,
    },
    {
        title: 'Cuti',
        url: '/attendance/leave-requests',
        icon: CalendarDays,
    },
    {
        title: 'Slip Gaji',
        url: '/payroll/my-payslips',
        icon: ReceiptText,
    },
];

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: {
    children: React.ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}) {
    const page = usePage();
    const currentPath = page.url.split('?')[0];

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="pb-20 md:pb-0">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>

            <nav
                aria-label="Mobile quick navigation"
                className="bg-background/95 border-sidebar-border/80 fixed inset-x-0 bottom-0 z-40 grid grid-cols-4 border-t backdrop-blur-sm md:hidden"
            >
                {mobileBottomItems.map((item) => {
                    const isActive = currentPath === item.url;
                    const Icon = item.icon;
                    return (
                        <Link
                            key={item.url}
                            href={item.url}
                            prefetch
                            className={cn(
                                'flex min-h-14 flex-col items-center justify-center gap-1 px-2 py-1.5 text-xs font-medium transition-colors',
                                isActive
                                    ? 'text-primary bg-primary/5'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <Icon className="size-5 shrink-0" />
                            <span className="truncate">{item.title}</span>
                        </Link>
                    );
                })}
            </nav>
        </AppShell>
    );
}
