import * as React from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import type { SharedProps, NavGroup } from '@/types';
import { adminUrl } from '@/lib/route';
import {
    Sidebar, SidebarProvider, SidebarTrigger, SidebarHeader, SidebarContent, SidebarFooter,
    SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuItem, SidebarMenuButton, SidebarInset,
} from '@/components/ui/sidebar';
import { Toaster } from '@/components/ui/sonner';
import { toast } from 'sonner';

export default function AppLayout({ children }: { children: React.ReactNode }) {
    const { navigation, auth, flash, adminPath } = usePage<SharedProps>().props;
    const current = usePage().url;

    React.useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash?.success, flash?.error]);

    return (
        <SidebarProvider>
            <div className="flex min-h-screen w-full bg-background text-foreground">
                <Sidebar>
                    <SidebarHeader>
                        <Link href={adminUrl('', adminPath)} className="text-lg font-semibold tracking-tight">
                            Sunrice
                        </Link>
                    </SidebarHeader>
                    <SidebarContent>
                        {(navigation ?? []).map((group: NavGroup) => (
                            <SidebarGroup key={group.label}>
                                <SidebarGroupLabel>{group.label}</SidebarGroupLabel>
                                <SidebarMenu>
                                    {group.items.map((item) => (
                                        <SidebarMenuItem key={item.href}>
                                            <SidebarMenuButton
                                                href={adminUrl(item.href, adminPath)}
                                                active={current.startsWith(adminUrl(item.href, adminPath))}
                                            >
                                                {item.label}
                                            </SidebarMenuButton>
                                        </SidebarMenuItem>
                                    ))}
                                </SidebarMenu>
                            </SidebarGroup>
                        ))}
                    </SidebarContent>
                    <SidebarFooter>
                        <div className="flex items-center justify-between gap-2 px-2 text-sm">
                            <span className="truncate text-muted-foreground">{auth.user?.name ?? auth.user?.email}</span>
                            <button
                                className="text-muted-foreground hover:text-foreground"
                                title="Log out"
                                onClick={() => router.post(adminUrl('logout', adminPath))}
                            >
                                <LogOut className="h-4 w-4" />
                            </button>
                        </div>
                    </SidebarFooter>
                </Sidebar>
                <SidebarInset>
                    <header className="flex h-12 items-center gap-2 border-b px-4">
                        <SidebarTrigger />
                    </header>
                    <main className="flex-1 overflow-y-auto p-6">{children}</main>
                </SidebarInset>
            </div>
            <Toaster position="top-right" />
        </SidebarProvider>
    );
}
