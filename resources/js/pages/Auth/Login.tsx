import { useForm, Link, usePage } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import AuthLayout from '@/layouts/AuthLayout';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { InputError } from '@/components/app/input-error';
import { adminUrl } from '@/lib/route';
import type { SharedProps } from '@/types';

export default function Login() {
    const { adminPath, flash } = usePage<SharedProps>().props;
    const form = useForm({ email: '', password: '', remember: false });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(adminUrl('login', adminPath), { onFinish: () => form.reset('password') });
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-6">
            {flash?.success && <div className="text-center text-sm font-medium text-green-600">{flash.success}</div>}
            {flash?.error && <div className="text-center text-sm font-medium text-destructive">{flash.error}</div>}
            <div className="grid gap-6">
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
                <div className="grid gap-2">
                    <div className="flex items-center">
                        <Label htmlFor="password">Password</Label>
                        <Link
                            href={adminUrl('forgot-password', adminPath)}
                            className="ml-auto text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                        >
                            Forgot password?
                        </Link>
                    </div>
                    <Input
                        id="password"
                        type="password"
                        autoComplete="current-password"
                        placeholder="Password"
                        value={form.data.password}
                        onChange={(e) => form.setData('password', e.target.value)}
                        required
                    />
                    <InputError message={form.errors.password} />
                </div>
                <div className="flex items-center gap-3">
                    <Checkbox id="remember" checked={form.data.remember} onCheckedChange={(checked) => form.setData('remember', checked === true)} />
                    <Label htmlFor="remember" className="font-normal">
                        Remember me
                    </Label>
                </div>
                <Button type="submit" disabled={form.processing} className="w-full">
                    {form.processing && <LoaderCircle className="animate-spin" />}
                    Log in
                </Button>
            </div>
        </form>
    );
}

Login.layout = (page: React.ReactNode) => <AuthLayout>{page}</AuthLayout>;
