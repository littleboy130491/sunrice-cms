import { router, usePage } from '@inertiajs/react';
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
        form.put(adminUrl(`roles/${role.id}`, adminPath));
    };

    return (
        <form onSubmit={submit} className="flex max-w-3xl flex-col gap-4">
            <h1 className="text-xl font-semibold tracking-tight">Edit role</h1>
            <div className="grid max-w-sm gap-2">
                <Label>Name</Label>
                <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
            </div>
            <div className="grid gap-4 md:grid-cols-2">
                {permissionGroups.map((g) => (
                    <Card key={g.group}>
                        <CardHeader>
                            <CardTitle className="flex items-center justify-between text-sm">
                                {g.group}
                                <button type="button" className="text-xs text-muted-foreground hover:underline" onClick={() => toggleGroup(g)}>
                                    toggle all
                                </button>
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2">
                            {g.permissions.map((p) => (
                                <label key={p.name} className="flex items-center gap-2 text-sm">
                                    <Checkbox checked={form.data.permissions.includes(p.name)} onCheckedChange={() => toggle(p.name)} />
                                    <span>{p.label}</span>
                                    <code className="ml-auto text-[10px] text-muted-foreground">{p.name}</code>
                                </label>
                            ))}
                        </CardContent>
                    </Card>
                ))}
            </div>
            {role.editable !== false && <div><Button type="submit" disabled={form.processing}>Save role</Button></div>}
        </form>
    );
}
