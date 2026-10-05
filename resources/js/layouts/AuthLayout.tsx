import * as React from 'react';
import { Toaster } from '@/components/ui/sonner';

export default function AuthLayout({ children }: { children: React.ReactNode }) {
    return (
        <div className="flex min-h-screen items-center justify-center bg-muted/40 p-6">
            <div className="w-full max-w-sm">
                <div className="mb-6 text-center text-2xl font-semibold">Sunrice</div>
                {children}
            </div>
            <Toaster position="top-right" />
        </div>
    );
}
