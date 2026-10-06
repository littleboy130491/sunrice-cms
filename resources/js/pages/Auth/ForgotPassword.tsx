import { useForm, Link, usePage } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import AuthLayout from '@/layouts/AuthLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { InputError } from '@/components/app/input-error';
import { adminUrl } from '@/lib/route';
import type { SharedProps } from '@/types';

export default function ForgotPassword() {
    const { adminPath, flash } = usePage<SharedProps>().props;
    const form = useForm({ email: '' });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(adminUrl('forgot-password', adminPath));
    };

    return (
        <div className="flex flex-col gap-6">
            {flash?.success && (
                <div className="rounded-md bg-emerald-500/10 px-3 py-2 text-center text-sm font-medium text-emerald-700 dark:text-emerald-400">
                    {flash.success}
                </div>
            )}
            <form onSubmit={submit} className="grid gap-6">
                <div className="grid gap-2">
                    <Label htmlFor="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        autoComplete="email"
                        autoFocus
                        placeholder="email@example.com"
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                        required
                    />
                    <InputError message={form.errors.email} />
                </div>
                <Button type="submit" disabled={form.processing} className="w-full">
                    {form.processing && <LoaderCircle className="animate-spin" />}
                    Email password reset link
                </Button>
            </form>
            <div className="text-center text-sm text-muted-foreground">
                Or, return to{' '}
                <Link href={adminUrl('login', adminPath)} className="text-foreground underline underline-offset-4">
                    log in
                </Link>
            </div>
        </div>
    );
}

ForgotPassword.layout = (page: React.ReactNode) => (
    <AuthLayout title="Forgot password" description="Enter your email to receive a password reset link.">
        {page}
    </AuthLayout>
);
