import { usePage } from '@inertiajs/react';
import { Sun } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { Branding, SharedProps } from '@/types';

const fallback: Branding = { name: 'Sunrice', tagline: 'Content workspace', logo: null, font: 'instrument-sans', color: null, is_default: true };

/** The panel's branding (Settings → Branding), with Sunrice's as the default. */
export function useBranding(): Branding {
    return usePage<SharedProps>().props.branding ?? fallback;
}

/** The logo image when one is set, else the Sunrice mark. */
export function AppLogoIcon({ className }: { className?: string }) {
    const { logo, name } = useBranding();

    return logo ? <img src={logo} alt={name} className={cn('object-contain', className)} /> : <Sun className={className} />;
}

export default function AppLogo() {
    const { name, tagline, logo, is_default } = useBranding();

    return (
        <>
            <div
                className={cn(
                    'flex aspect-square size-8 items-center justify-center overflow-hidden rounded-xl shadow-sm',
                    logo ? 'bg-card' : 'bg-sidebar-primary text-sidebar-primary-foreground',
                )}
            >
                <AppLogoIcon className={logo ? 'size-8' : 'size-5'} />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="truncate text-base leading-tight font-semibold tracking-tight">
                    {name}
                    {is_default && <span className="text-muted-foreground">.</span>}
                </span>
                {tagline && <span className="truncate text-[11px] text-muted-foreground">{tagline}</span>}
            </div>
        </>
    );
}
