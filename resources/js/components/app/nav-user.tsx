import { router, usePage } from '@inertiajs/react';
import { ChevronsUpDown, LogOut, Monitor, Moon, Sun } from 'lucide-react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel,
    DropdownMenuRadioGroup, DropdownMenuRadioItem, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar } from '@/components/ui/sidebar';
import { useAppearance, type Appearance } from '@/hooks/use-appearance';
import { adminUrl } from '@/lib/route';
import type { SharedProps, User } from '@/types';

function initials(user: User | null): string {
    const source = user?.name || user?.email || '?';
    return source
        .split(/[\s@.]+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]!.toUpperCase())
        .join('');
}

function UserInfo({ user }: { user: User | null }) {
    return (
        <>
            <Avatar className="size-8 rounded-full">
                <AvatarFallback className="rounded-full bg-sidebar-accent text-xs font-medium text-sidebar-accent-foreground">{initials(user)}</AvatarFallback>
            </Avatar>
            <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-medium">{user?.name ?? user?.email}</span>
                {user?.name && <span className="truncate text-xs text-muted-foreground">{user.email}</span>}
            </div>
        </>
    );
}

export function NavUser() {
    const { auth, adminPath } = usePage<SharedProps>().props;
    const { isMobile, state } = useSidebar();
    const { appearance, updateAppearance } = useAppearance();

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton size="lg" className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground">
                            <UserInfo user={auth.user} />
                            <ChevronsUpDown className="ml-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                        align="end"
                        side={isMobile ? 'bottom' : state === 'collapsed' ? 'left' : 'bottom'}
                    >
                        <DropdownMenuLabel className="p-0 font-normal">
                            <div className="flex items-center gap-2 px-1 py-1.5">
                                <UserInfo user={auth.user} />
                            </div>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuGroup>
                            <DropdownMenuLabel className="text-xs font-normal text-muted-foreground">Appearance</DropdownMenuLabel>
                            <DropdownMenuRadioGroup value={appearance} onValueChange={(value) => updateAppearance(value as Appearance)}>
                                <DropdownMenuRadioItem value="light">
                                    <Sun /> Light
                                </DropdownMenuRadioItem>
                                <DropdownMenuRadioItem value="dark">
                                    <Moon /> Dark
                                </DropdownMenuRadioItem>
                                <DropdownMenuRadioItem value="system">
                                    <Monitor /> System
                                </DropdownMenuRadioItem>
                            </DropdownMenuRadioGroup>
                        </DropdownMenuGroup>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem onSelect={() => router.post(adminUrl('logout', adminPath))}>
                            <LogOut /> Log out
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
