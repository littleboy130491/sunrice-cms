import { useForm } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { CollapsibleCard } from '@/components/app/collapsible-card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { SettingsTabs } from '@/components/app/settings-tabs';
import { adminUrl } from '@/lib/route';
import type { SharedProps } from '@/types';

type Role = 'title' | 'description' | 'image';
interface Field { handle: string; label: string; type: string }
interface Row {
    kind: 'collection' | 'taxonomy';
    id: number;
    title: string;
    blueprint: string | null;
    fields: Record<Role, Field[]>;
    chosen: Record<Role, string>;
    resolved: Record<Role, string | null>;
}

interface Props { collections: Row[]; taxonomies: Row[] }

const ROLES: { role: Role; label: string; hint: string }[] = [
    { role: 'title', label: 'Meta title', hint: 'Automatic uses the title.' },
    { role: 'description', label: 'Meta description', hint: 'Text is cut to 160 characters.' },
    { role: 'image', label: 'Share image', hint: 'The first image of the field.' },
];

export default function SeoSettings({ collections, taxonomies }: Props) {
    const { adminPath } = usePage<SharedProps>().props;
    const form = useForm({ items: [...collections, ...taxonomies].map((r) => ({ kind: r.kind, id: r.id, chosen: { ...r.chosen } })) });
    const rows = [...collections, ...taxonomies];

    const setChoice = (index: number, role: Role, value: string) =>
        form.setData('items', form.data.items.map((item, i) => (i === index ? { ...item, chosen: { ...item.chosen, [role]: value } } : item)));

    const labelOf = (row: Row, role: Role, handle: string | null) =>
        handle === null ? (role === 'title' ? 'the title' : 'nothing') : row.fields[role].find((f) => f.handle === handle)?.label ?? handle;

    const section = (title: string, description: string, list: Row[], offset: number) =>
        list.length > 0 && (
            <CollapsibleCard title={title} description={description} storageKey={`settings:seo:${title.toLowerCase()}`} contentClassName="flex flex-col divide-y">
                {list.map((row, i) => {
                    const index = offset + i;
                    return (
                        <div key={`${row.kind}-${row.id}`} className="grid gap-3 py-4 first:pt-0 last:pb-0">
                            <div className="flex items-baseline justify-between gap-2">
                                <span className="font-medium">{row.title}</span>
                                <span className="text-xs text-muted-foreground">{row.blueprint ? `Blueprint: ${row.blueprint}` : 'No blueprint'}</span>
                            </div>
                            <div className="grid gap-3 sm:grid-cols-3">
                                {ROLES.map(({ role, label }) => {
                                    const value = form.data.items[index].chosen[role] || 'auto';
                                    return (
                                        <div key={role} className="grid gap-1.5">
                                            <span className="text-xs font-medium text-muted-foreground">{label}</span>
                                            <Select value={value} onValueChange={(v) => setChoice(index, role, v === 'auto' ? '' : v)}>
                                                <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="auto">{row.chosen[role] ? 'Automatic' : `Automatic (${labelOf(row, role, row.resolved[role])})`}</SelectItem>
                                                    <SelectItem value="none">None</SelectItem>
                                                    {row.fields[role].map((f) => <SelectItem key={f.handle} value={f.handle}>{f.label}</SelectItem>)}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    );
                })}
            </CollapsibleCard>
        );

    return (
        <form
            onSubmit={(e) => { e.preventDefault(); form.put(adminUrl('settings/seo', adminPath), { preserveScroll: true }); }}
            className="flex max-w-4xl flex-col gap-6"
        >
            <div className="flex items-center justify-between">
                <h1 className="sunrice-page-title">Settings</h1>
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <LoaderCircle className="animate-spin" />} Save SEO fields
                </Button>
            </div>
            <SettingsTabs current="seo" />
            <p className="text-sm text-muted-foreground">
                When an entry or term leaves its meta title, description or share image empty, the site uses one of its fields
                instead. <strong>Automatic</strong> picks a fitting field (e.g. <code>excerpt</code> or <code>summary</code> for the
                description, the first image field for the share image). The page's own SEO fields always win; after the field come
                the collection's or taxonomy's defaults (Structure) and then the site's (General).
            </p>
            {rows.length === 0 && <p className="text-sm text-muted-foreground">No collections with pages or taxonomies with term pages yet.</p>}
            {section('Collections', 'Entries of each collection.', collections, 0)}
            {section('Taxonomies', 'Term pages of each taxonomy.', taxonomies, collections.length)}
            <p className="text-xs text-muted-foreground">{ROLES.map((r) => `${r.label}: ${r.hint}`).join(' ')}</p>
        </form>
    );
}
