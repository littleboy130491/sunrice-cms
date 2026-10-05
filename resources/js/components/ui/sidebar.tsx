import * as React from 'react';
import { PanelLeft } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';

type SidebarContextValue = { open: boolean; setOpen: (open: boolean) => void };
const SidebarContext = React.createContext<SidebarContextValue | null>(null);

function useSidebar() {
    const ctx = React.useContext(SidebarContext);
    if (!ctx) throw new Error('useSidebar must be used within SidebarProvider');
    return ctx;
}

const SidebarProvider = ({ defaultOpen = true, children }: { defaultOpen?: boolean; children: React.ReactNode }) => {
    const [open, setOpen] = React.useState(defaultOpen);
    return <SidebarContext.Provider value={{ open, setOpen }}>{children}</SidebarContext.Provider>;
};

const Sidebar = React.forwardRef<HTMLDivElement, React.HTMLAttributes<HTMLDivElement>>(({ className, ...props }, ref) => {
    const { open } = useSidebar();
    return (
        <aside
            ref={ref}
            data-state={open ? 'expanded' : 'collapsed'}
            className={cn(
                'hidden md:flex flex-col border-r bg-sidebar text-sidebar-foreground transition-[width] duration-200',
                open ? 'w-64' : 'w-14',
                className,
            )}
            {...props}
        />
    );
});
Sidebar.displayName = 'Sidebar';

const SidebarTrigger = React.forwardRef<HTMLButtonElement, React.ComponentProps<typeof Button>>(({ className, ...props }, ref) => {
    const { open, setOpen } = useSidebar();
    return (
        <Button ref={ref} variant="ghost" size="icon" className={className} onClick={() => setOpen(!open)} {...props}>
            <PanelLeft className="h-4 w-4" />
            <span className="sr-only">Toggle sidebar</span>
        </Button>
    );
});
SidebarTrigger.displayName = 'SidebarTrigger';

const SidebarHeader = ({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) => (
    <div className={cn('flex flex-col gap-2 p-4', className)} {...props} />
);
const SidebarFooter = ({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) => (
    <div className={cn('mt-auto flex flex-col gap-2 p-4', className)} {...props} />
);
const SidebarContent = ({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) => (
    <div className={cn('flex flex-1 flex-col gap-2 overflow-y-auto p-4 pt-0', className)} {...props} />
);
const SidebarGroup = ({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) => (
    <div className={cn('flex flex-col gap-1', className)} {...props} />
);
const SidebarGroupLabel = ({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) => (
    <div className={cn('px-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground', className)} {...props} />
);
const SidebarMenu = ({ className, ...props }: React.HTMLAttributes<HTMLUListElement>) => (
    <ul className={cn('flex flex-col gap-1', className)} {...props} />
);
const SidebarMenuItem = ({ className, ...props }: React.HTMLAttributes<HTMLLIElement>) => (
    <li className={cn('list-none', className)} {...props} />
);
const SidebarMenuButton = React.forwardRef<HTMLAnchorElement, React.AnchorHTMLAttributes<HTMLAnchorElement> & { active?: boolean }>(
    ({ className, active, ...props }, ref) => (
        <a
            ref={ref}
            className={cn(
                'flex items-center gap-2 rounded-md px-2 py-1.5 text-sm transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                active && 'bg-sidebar-accent font-medium text-sidebar-accent-foreground',
                className,
            )}
            {...props}
        />
    ),
);
SidebarMenuButton.displayName = 'SidebarMenuButton';

const SidebarInset = ({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) => (
    <div className={cn('flex min-h-0 flex-1 flex-col', className)} {...props} />
);

export {
    Sidebar, SidebarProvider, SidebarTrigger, SidebarHeader, SidebarFooter, SidebarContent,
    SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuItem, SidebarMenuButton, SidebarInset,
    useSidebar,
};
