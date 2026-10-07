import * as React from 'react';
import { useForm, Link, router, usePage } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import AuthLayout from '@/layouts/AuthLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { InputError } from '@/components/app/input-error';
import { adminUrl } from '@/lib/route';
import type { SharedProps } from '@/types';

interface Props {
    email: string;
    resendIn: number;
    minutes: number;
}

/** Two-factor login: enter the code emailed after a correct password. */
export default function TwoFactor({ email, resendIn, minutes }: Props) {
    const { adminPath, flash } = usePage<SharedProps>().props;
    const form = useForm({ code: '' });
    const [wait, setWait] = React.useState(resendIn);
    const [resending, setResending] = React.useState(false);

    // Count down to when another code may be sent.
    React.useEffect(() => setWait(resendIn), [resendIn, flash?.id]);
    React.useEffect(() => {
        if (wait <= 0) return;
        const timer = window.setTimeout(() => setWait((w) => w - 1), 1000);
        return () => window.clearTimeout(timer);
    }, [wait]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(adminUrl('login/code', adminPath), { onError: () => form.reset('code') });
    };

    const resend = () => {
        router.post(adminUrl('login/code/resend', adminPath), {}, {
            preserveScroll: true,
            onStart: () => setResending(true),
            onFinish: () => setResending(false),
        });
    };

    return (
        <div className="flex flex-col gap-6">
            {flash?.success && (
                <div className="rounded-md bg-emerald-500/10 px-3 py-2 text-center text-sm font-medium text-emerald-700 dark:text-emerald-400">
                    {flash.success}
                </div>
            )}
            {flash?.error && <div className="text-center text-sm font-medium text-destructive">{flash.error}</div>}
            <p className="text-sm text-muted-foreground">
                We sent a 6-digit code to <span className="font-medium text-foreground">{email}</span>. It expires in {minutes} minutes.
            </p>
            <form onSubmit={submit} className="grid gap-6">
                <div className="grid gap-2">
                    <Label htmlFor="code">Login code</Label>
                    <Input
                        id="code"
                        inputMode="numeric"
                        autoComplete="one-time-code"
                        autoFocus
                        maxLength={7}
                        placeholder="123456"
                        className="text-center font-mono text-lg tracking-[0.4em]"
                        value={form.data.code}
                        onChange={(e) => form.setData('code', e.target.value.replace(/[^\d ]/g, ''))}
                        required
                    />
                    <InputError message={form.errors.code} />
                </div>
                <Button type="submit" disabled={form.processing || form.data.code.replace(/\D/g, '').length < 6} className="w-full">
                    {form.processing && <LoaderCircle className="animate-spin" />}
                    Verify and log in
                </Button>
            </form>
            <div className="flex flex-col items-center gap-2 text-center text-sm text-muted-foreground">
                <p>
                    Didn't get it? Check your spam folder, or{' '}
                    <button
                        type="button"
                        onClick={resend}
                        disabled={wait > 0 || resending}
                        className="text-foreground underline underline-offset-4 disabled:cursor-not-allowed disabled:text-muted-foreground disabled:no-underline"
                    >
                        {wait > 0 ? `send a new code in ${wait}s` : 'send a new code'}
                    </button>
                    .
                </p>
                <Link href={adminUrl('login', adminPath)} className="underline-offset-4 hover:text-foreground hover:underline">
                    Back to log in
                </Link>
            </div>
        </div>
    );
}

TwoFactor.layout = (page: React.ReactNode) => (
    <AuthLayout title="Check your email" description="Enter the login code we just emailed you.">
        {page}
    </AuthLayout>
);
