import { useForm, usePage } from '@inertiajs/react';
import AuthLayout from '@/layouts/AuthLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent } from '@/components/ui/card';
import { adminUrl } from '@/lib/route';
import type { SharedProps } from '@/types';

export default function ResetPassword({ token, email }: { token: string; email: string }) {
    const { adminPath } = usePage<SharedProps>().props;
    const form = useForm({ token, email, password: '', password_confirmation: '' });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(adminUrl('reset-password', adminPath));
    };

    return (
        <Card>
            <CardContent className="pt-6">
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="email">Email</Label>
                        <Input id="email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} required />
                        {form.errors.email && <p className="text-sm text-destructive">{form.errors.email}</p>}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="password">New password</Label>
                        <Input id="password" type="password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} required />
                        {form.errors.password && <p className="text-sm text-destructive">{form.errors.password}</p>}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation">Confirm password</Label>
                        <Input id="password_confirmation" type="password" value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} required />
                    </div>
                    <Button type="submit" disabled={form.processing} className="w-full">
                        Reset password
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

ResetPassword.layout = (page: React.ReactNode) => <AuthLayout>{page}</AuthLayout>;
