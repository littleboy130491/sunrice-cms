import { useForm, Link, usePage } from '@inertiajs/react';
import AuthLayout from '@/layouts/AuthLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent } from '@/components/ui/card';
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
        <Card>
            <CardContent className="pt-6">
                {flash?.success && <p className="mb-4 text-sm text-emerald-600">{flash.success}</p>}
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="email">Email</Label>
                        <Input id="email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} required />
                        {form.errors.email && <p className="text-sm text-destructive">{form.errors.email}</p>}
                    </div>
                    <Button type="submit" disabled={form.processing} className="w-full">
                        Send reset link
                    </Button>
                    <div className="text-center">
                        <Link href={adminUrl('login', adminPath)} className="text-sm text-muted-foreground hover:underline">
                            Back to login
                        </Link>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

ForgotPassword.layout = (page: React.ReactNode) => <AuthLayout>{page}</AuthLayout>;
