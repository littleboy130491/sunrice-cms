import * as React from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { LayoutGrid } from 'lucide-react';
import {
    Sidebar, SidebarContent, SidebarFooter, SidebarGroup, SidebarGroupLabel, SidebarHeader, SidebarMenu,
    SidebarMenuButton, SidebarMenuItem, SidebarRail, useSidebar,
} from '@/components/ui/sidebar';
import AppLogo from '@/components/app/app-logo';
import { NavUser } from '@/components/app/nav-user';
import { navIcon } from '@/components/app/nav-icon';
import { useNavMatch } from '@/components/app/use-nav-match';
import { adminUrl } from '@/lib/route';
import type { NavGroup, SharedProps } from '@/types';

export function AppSidebar() {
    const { navigation, adminPath } = usePage<SharedProps>().props;
    const match = useNavMatch();
    const dashboardUrl = adminUrl('', adminPath);
    const onDashboard = usePage().url.split('?')[0] === dashboardUrl;
    const { isMobile, setOpenMobile } = useSidebar();

    // On phones the sidebar is a flyout: close it as soon as a link in it
    // (or anywhere) starts a visit, instead of leaving it over the new page.
    React.useEffect(() => {
        if (!isMobile) return;
        return router.on('start', () => setOpenMobile(false));
    }, [isMobile, setOpenMobile]);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader className="pt-4 pb-5">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboardUrl} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-5">
                <SidebarGroup className="px-2 py-0">
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild isActive={onDashboard} tooltip="Dashboard">
                                <Link href={dashboardUrl} prefetch>
                                    <LayoutGrid />
                                    <span>Dashboard</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>

                {(navigation ?? []).map((group: NavGroup) => (
                    <SidebarGroup key={group.label} className="px-2 py-0">
                        <SidebarGroupLabel className="mb-1 text-[10px] font-semibold tracking-[0.14em] uppercase">{group.label}</SidebarGroupLabel>
                        <SidebarMenu>
                            {group.items.map((item) => {
                                const Icon = navIcon(item.icon);
                                return (
                                    <SidebarMenuItem key={`${group.label}:${item.href}`}>
                                        <SidebarMenuButton
                                            asChild
                                            isActive={match?.item === item}
                                            tooltip={item.label}
                                        >
                                            <Link href={adminUrl(item.href, adminPath)} prefetch>
                                                <Icon />
                                                <span>{item.label}</span>
                                            </Link>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                );
                            })}
                        </SidebarMenu>
                    </SidebarGroup>
                ))}
            </SidebarContent>

            <SidebarFooter className="border-t border-sidebar-border/60 py-3">
                <NavUser />
            </SidebarFooter>
            <SidebarRail />
        </Sidebar>
    );
}
