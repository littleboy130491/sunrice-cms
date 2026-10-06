import { useForm, usePage } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import AuthLayout from '@/layouts/AuthLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { InputError } from '@/components/app/input-error';
import { adminUrl } from '@/lib/route';
import type { SharedProps } from '@/types';

export default function ResetPassword({ token, email }: { token: string; email: string }) {
    const { adminPath } = usePage<SharedProps>().props;
    const form = useForm({ token, email, password: '', password_confirmation: '' });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(adminUrl('reset-password', adminPath), { onFinish: () => form.reset('password', 'password_confirmation') });
    };

    return (
        <form onSubmit={submit} className="grid gap-6">
            <div className="grid gap-2">
                <Label htmlFor="email">Email</Label>
                <Input id="email" type="email" autoComplete="email" value={form.data.email} readOnly className="bg-muted/50" />
                <InputError message={form.errors.email} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="password">New password</Label>
                <Input
                    id="password"
                    type="password"
                    autoComplete="new-password"
                    autoFocus
                    placeholder="Password"
                    value={form.data.password}
                    onChange={(e) => form.setData('password', e.target.value)}
                    required
                />
                <InputError message={form.errors.password} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="password_confirmation">Confirm password</Label>
                <Input
                    id="password_confirmation"
                    type="password"
                    autoComplete="new-password"
                    placeholder="Confirm password"
                    value={form.data.password_confirmation}
                    onChange={(e) => form.setData('password_confirmation', e.target.value)}
                    required
                />
                <InputError message={form.errors.password_confirmation} />
            </div>
            <Button type="submit" disabled={form.processing} className="w-full">
                {form.processing && <LoaderCircle className="animate-spin" />}
                Reset password
            </Button>
        </form>
    );
}

ResetPassword.layout = (page: React.ReactNode) => (
    <AuthLayout title="Reset password" description="Please enter your new password below.">
        {page}
    </AuthLayout>
);
