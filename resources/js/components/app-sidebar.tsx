import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { CalendarDays, Clock, LayoutGrid, ReceiptText, Users } from 'lucide-react';
import AppLogo from './app-logo';

const essNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        url: '/dashboard',
        icon: LayoutGrid,
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

    const hrAdminNavItems: NavItem[] = [];
    if (permissions.includes('employee.view')) {
        hrAdminNavItems.push({
            title: 'Employee Directory',
            url: '/core-hr/employees',
            icon: Users,
        });
    }

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
                {hrAdminNavItems.length > 0 && <NavMain label="HR Management" items={hrAdminNavItems} />}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
