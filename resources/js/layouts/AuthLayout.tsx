import * as React from 'react';
import { Head } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { AppLogoIcon, useBranding } from '@/components/app/app-logo';
import { AtmosphereArt } from '@/components/app/atmosphere-art';

interface AuthLayoutProps {
    children: React.ReactNode;
    title?: string;
    description?: string;
}

export default function AuthLayout({ children, title, description = 'Enter your email and password below to log in.' }: AuthLayoutProps) {
    const brand = useBranding();
    title ??= `Log in to ${brand.name}`;
    const mark = (
        <>
            <AppLogoIcon className="size-6" /> {brand.name}{brand.is_default && '.'}
        </>
    );

    return (
        <div className="sunrice-shell grid min-h-svh bg-background p-4 lg:grid-cols-2 lg:gap-4">
            <Head title={title} />
            <aside className="sunrice-atmosphere relative hidden flex-col justify-between overflow-hidden rounded-xl p-10 lg:flex xl:p-14">
                <div className="flex items-center gap-3 text-lg font-semibold tracking-tight">{mark}</div>
                <div className="relative z-10 mt-12">
                    <p className="mb-5 text-xs font-medium tracking-[0.18em] uppercase">A little space to create</p>
                    <h2 className="max-w-md text-5xl leading-[1.08] font-medium tracking-[-0.05em] xl:text-6xl">Good content.<br /><span className="opacity-60">Beautifully managed.</span></h2>
                    <p className="mt-6 max-w-xs text-sm leading-relaxed opacity-70">One calm workspace for your content, your team, and everything you’re ready to share.</p>
                </div>
                <AtmosphereArt className="my-4 h-72 w-full self-center xl:h-80" />
                <p className="relative z-10 text-xs opacity-60">Your content, in good hands.</p>
            </aside>
            <div className="flex flex-col items-center justify-center px-4 py-12 sm:px-8">
                <div className="w-full max-w-sm">
                    <div className="mb-12 flex items-center gap-2 text-lg font-semibold tracking-tight lg:hidden">{mark}</div>
                    <div className="flex flex-col gap-8">
                        <div className="space-y-3">
                            <p className="text-[10px] font-semibold tracking-[0.16em] text-muted-foreground uppercase">Your content workspace</p>
                            <h1 className="text-3xl font-medium tracking-[-0.04em]">{title}</h1>
                            <p className="text-sm leading-relaxed text-muted-foreground">{description}</p>
                        </div>
                        {children}
                    </div>
                    {brand.is_default && <p className="mt-10 text-xs text-muted-foreground">Powered by Sunrice CMS</p>}
                </div>
            </div>
            <Toaster position="top-right" />
        </div>
    );
}
