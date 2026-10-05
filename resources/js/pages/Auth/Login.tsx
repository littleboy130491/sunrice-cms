import { useForm, Link, usePage } from '@inertiajs/react';
import AuthLayout from '@/layouts/AuthLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent } from '@/components/ui/card';
import { adminUrl } from '@/lib/route';
import type { SharedProps } from '@/types';

export default function Login() {
    const { adminPath } = usePage<SharedProps>().props;
    const form = useForm({ email: '', password: '', remember: false });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(adminUrl('login', adminPath));
    };

    return (
        <Card>
            <CardContent className="pt-6">
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="email">Email</Label>
                        <Input
                            id="email"
                            type="email"
                            autoComplete="email"
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                            required
                        />
                        {form.errors.email && <p className="text-sm text-destructive">{form.errors.email}</p>}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="password">Password</Label>
                        <Input
                            id="password"
                            type="password"
                            autoComplete="current-password"
                            value={form.data.password}
                            onChange={(e) => form.setData('password', e.target.value)}
                            required
                        />
                        {form.errors.password && <p className="text-sm text-destructive">{form.errors.password}</p>}
                    </div>
                    <Button type="submit" disabled={form.processing} className="w-full">
                        Log in
                    </Button>
                    <div className="text-center">
                        <Link href={adminUrl('forgot-password', adminPath)} className="text-sm text-muted-foreground hover:underline">
                            Forgot your password?
                        </Link>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

Login.layout = (page: React.ReactNode) => <AuthLayout>{page}</AuthLayout>;
