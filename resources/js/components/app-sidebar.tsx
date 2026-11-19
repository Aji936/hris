import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { type NavItem, type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { CalendarDays, Clock, Landmark, LayoutGrid, LogOut, Megaphone, ReceiptText, Users } from 'lucide-react';
import AppLogo from './app-logo';

const essNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        url: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Pengumuman',
        url: '/announcements',
        icon: Megaphone,
    },
    {
        title: 'Daily Attendance',
        url: '/attendance',
        icon: Clock,
    },
    {
        title: 'Leave Requests',
        url: '/attendance/leave-requests',
        icon: CalendarDays,
    },
    {
        title: 'My Payslips',
        url: '/payroll/my-payslips',
        icon: ReceiptText,
    },
];

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const permissions = auth.permissions ?? [];
    const cleanup = useMobileNavigation();

    const hrAdminNavItems: NavItem[] = [];
    if (permissions.includes('employee.view')) {
        hrAdminNavItems.push({
            title: 'Employee & Master Data',
            url: '/core-hr/employees',
            icon: Users,
        });
        hrAdminNavItems.push({
            title: 'Kelola Pengumuman',
            url: '/announcements',
            icon: Megaphone,
        });
    }
    if (permissions.includes('payroll.view')) {
        hrAdminNavItems.push({
            title: 'Payroll & Compensation',
            url: '/payroll/management',
            icon: Landmark,
        });
    }

    const handleLogout = (): void => {
        cleanup();
        const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
        router.post(
            route('logout', undefined, false),
            csrfToken ? { _token: csrfToken } : {},
            {
                preserveState: false,
                preserveScroll: false,
                onError: () => {
                    window.location.href = '/login';
                },
            },
        );
    };

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-4">
                <NavMain label="Employee Self-Service" items={essNavItems} />
                {hrAdminNavItems.length > 0 && <NavMain label="HR & Payroll Admin" items={hrAdminNavItems} />}
            </SidebarContent>

            <SidebarFooter className="gap-2">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            type="button"
                            onClick={handleLogout}
                            tooltip="Log Out"
                            className="text-destructive hover:bg-destructive/10 hover:text-destructive min-h-10 font-medium"
                        >
                            <LogOut className="size-4 shrink-0" />
                            <span>Log Out / Keluar</span>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
