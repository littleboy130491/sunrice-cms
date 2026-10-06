import * as React from 'react';
import { Head } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { AppLogoIcon } from '@/components/app/app-logo';

interface AuthLayoutProps {
    children: React.ReactNode;
    title?: string;
    description?: string;
}

export default function AuthLayout({ children, title = 'Log in to Sunrice', description = 'Enter your email and password below to log in.' }: AuthLayoutProps) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
            <Head title={title} />
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <div className="flex size-10 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                            <AppLogoIcon className="size-5" />
                        </div>
                        <div className="space-y-2 text-center">
                            <h1 className="text-xl font-medium">{title}</h1>
                            <p className="text-center text-sm text-muted-foreground">{description}</p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
            <Toaster position="top-right" />
        </div>
    );
}
