import { Sun } from 'lucide-react';

export function AppLogoIcon({ className }: { className?: string }) {
    return <Sun className={className} />;
}

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-xl bg-sidebar-primary text-sidebar-primary-foreground shadow-sm">
                <AppLogoIcon className="size-5" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="truncate text-base leading-tight font-semibold tracking-tight">Sunrice<span className="text-muted-foreground">.</span></span>
                <span className="truncate text-[11px] text-muted-foreground">Content workspace</span>
            </div>
        </>
    );
}
