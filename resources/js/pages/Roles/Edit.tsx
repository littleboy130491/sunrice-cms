import { usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { adminUrl } from '@/lib/route';
import { useForm } from '@inertiajs/react';
import type { SharedProps } from '@/types';

interface Group { group: string; permissions: { name: string; label: string }[] }

export default function RoleEdit({ role, permissionGroups }: { role: { id: number; name: string; permissions: string[]; editable?: boolean }; permissionGroups: Group[] }) {
    const { adminPath } = usePage<SharedProps>().props;
    const form = useForm({ name: role.name, permissions: role.permissions });
    const editable = role.editable !== false;

    const toggle = (name: string) =>
        form.setData('permissions', form.data.permissions.includes(name)
            ? form.data.permissions.filter((p) => p !== name)
            : [...form.data.permissions, name]);

    const toggleGroup = (g: Group) => {
        const all = g.permissions.map((p) => p.name);
        const checked = all.every((n) => form.data.permissions.includes(n));
        form.setData('permissions', checked
            ? form.data.permissions.filter((p) => !all.includes(p))
            : [...new Set([...form.data.permissions, ...all])]);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editable) return;
        form.put(adminUrl(`roles/${role.id}`, adminPath), { preserveScroll: true });
    };
    const permissionErrors = Object.entries(form.errors as Record<string, string>)
        .filter(([key]) => key === 'permissions' || key.startsWith('permissions.'))
        .map(([, message]) => message);

    return (
        <form onSubmit={submit} className="flex max-w-3xl flex-col gap-4">
            <h1 className="sunrice-page-title">{editable ? 'Edit role' : role.name}</h1>
            {!editable && <p className="text-sm text-muted-foreground">This role can't be edited. Super admins always have every permission.</p>}
            <div className="grid max-w-sm gap-2">
                <Label>Name</Label>
                <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required disabled={!editable} />
                {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
            </div>
            {permissionErrors.length > 0 && (
                <div className="rounded-md border border-destructive/50 p-3 text-sm text-destructive">
                    {[...new Set(permissionErrors)].map((m) => <p key={m}>{m}</p>)}
                </div>
            )}
            <div className="grid gap-4 md:grid-cols-2">
                {permissionGroups.map((g) => (
                    <Card key={g.group}>
                        <CardHeader>
                            <CardTitle className="flex items-center justify-between text-sm">
                                {g.group}
                                {editable && (
                                    <button type="button" className="text-xs text-muted-foreground hover:underline" onClick={() => toggleGroup(g)}>
                                        toggle all
                                    </button>
                                )}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2">
                            {g.permissions.map((p) => (
                                <label key={p.name} className="flex items-center gap-2 text-sm">
                                    <Checkbox checked={form.data.permissions.includes(p.name)} onCheckedChange={() => toggle(p.name)} disabled={!editable} />
                                    <span>{p.label}</span>
                                    <code className="ml-auto text-[10px] text-muted-foreground">{p.name}</code>
                                </label>
                            ))}
                        </CardContent>
                    </Card>
                ))}
            </div>
            {editable && <div><Button type="submit" disabled={form.processing}>{form.processing ? 'Saving…' : 'Save role'}</Button></div>}
        </form>
    );
}
