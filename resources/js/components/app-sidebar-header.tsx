import { Breadcrumbs } from '@/components/breadcrumbs';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';
import { router } from '@inertiajs/react';
import { LogOut } from 'lucide-react';

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItemType[] }) {
    const handleLogout = (): void => {
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
        <header className="border-sidebar-border/50 flex h-16 shrink-0 items-center justify-between gap-2 border-b px-3 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 sm:px-4">
            <div className="flex min-w-0 items-center gap-2">
                <SidebarTrigger className="-ml-1 min-h-11 min-w-11 shrink-0" />
                <div className="min-w-0 truncate">
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
            </div>

            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={handleLogout}
                aria-label="Log out"
                title="Log out"
                className="text-destructive border-destructive/30 hover:bg-destructive/10 hover:text-destructive min-h-10 shrink-0 gap-1.5 px-2.5 text-xs sm:px-3 sm:text-sm"
            >
                <LogOut className="size-4 shrink-0" />
                <span>Logout</span>
            </Button>
        </header>
    );
}
