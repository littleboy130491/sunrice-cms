import * as React from 'react';
import { Head, router, usePage } from '@inertiajs/react';
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

const shownFlashIds = new Set<string>();

export default function AppLayout({ children }: { children: React.ReactNode }) {
    const { flash } = usePage<SharedProps>().props;
    const match = useNavMatch();

    React.useEffect(() => {
        // Back/forward restores old page props, flash included: show each
        // flashed message once.
        if (flash?.id) {
            if (shownFlashIds.has(flash.id)) return;
            shownFlashIds.add(flash.id);
        }
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash?.id, flash?.success, flash?.error]);

    // A rejected save is never silent: pages show field errors inline where
    // they can, and this toast covers the rest (hidden fields, dialogs…).
    React.useEffect(
        () =>
            router.on('error', (event) => {
                const messages = Object.values(event.detail.errors ?? {}).filter(Boolean) as string[];
                if (messages.length === 0) return;
                toast.error(messages.length === 1 ? messages[0] : `${messages[0]} (+${messages.length - 1} more)`);
            }),
        [],
    );

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
