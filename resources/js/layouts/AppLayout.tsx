import * as React from 'react';
import { Head, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { AppSidebar } from '@/components/app/app-sidebar';
import { AppHeader } from '@/components/app/app-header';
import { useNavMatch } from '@/components/app/use-nav-match';
import { BreadcrumbProvider } from '@/components/app/breadcrumbs';
import type { SharedProps } from '@/types';

/** Read the sidebar's persisted open/collapsed state (shadcn stores it in a cookie). */
function sidebarDefaultOpen(): boolean {
    if (typeof document === 'undefined') return true;
    return !document.cookie.split('; ').includes('sidebar_state=false');
}

export default function AppLayout({ children }: { children: React.ReactNode }) {
    const { flash } = usePage<SharedProps>().props;
    const match = useNavMatch();

    React.useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash?.success, flash?.error]);

    return (
        <TooltipProvider delayDuration={0}>
            <BreadcrumbProvider>
            <SidebarProvider defaultOpen={sidebarDefaultOpen()}>
                <Head title={match?.item.label ?? 'Dashboard'} />
                <AppSidebar />
                <SidebarInset className="overflow-x-hidden">
                    <AppHeader />
                    <main className="mx-auto flex w-full max-w-7xl flex-1 flex-col p-4 md:p-6">{children}</main>
                </SidebarInset>
                <Toaster position="top-right" />
            </SidebarProvider>
            </BreadcrumbProvider>
        </TooltipProvider>
    );
}
