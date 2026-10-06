import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid } from 'lucide-react';
import {
    Sidebar, SidebarContent, SidebarFooter, SidebarGroup, SidebarGroupLabel, SidebarHeader, SidebarMenu,
    SidebarMenuButton, SidebarMenuItem, SidebarRail,
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

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
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

            <SidebarContent>
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
                        <SidebarGroupLabel>{group.label}</SidebarGroupLabel>
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

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
            <SidebarRail />
        </Sidebar>
    );
}
