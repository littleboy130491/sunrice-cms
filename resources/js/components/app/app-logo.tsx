import { Sun } from 'lucide-react';

export function AppLogoIcon({ className }: { className?: string }) {
    return <Sun className={className} />;
}

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                <AppLogoIcon className="size-4" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="truncate leading-tight font-semibold">Sunrice</span>
                <span className="truncate text-xs text-muted-foreground">Content</span>
            </div>
        </>
    );
}
