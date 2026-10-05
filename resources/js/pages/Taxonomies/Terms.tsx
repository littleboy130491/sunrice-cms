import * as React from 'react';
import { router, usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import FieldRenderer from '@/fields/FieldRenderer';
import { adminUrl } from '@/lib/route';
import type { AdminTab, SharedProps } from '@/types'; import type { Json } from '@/types';

interface TermRow {
    id: number;
    parent_id: number | null;
    translations: Record<string, { title: string; slug: string; data: Record<string, Json> }>;
    count: number;
}

interface Props {
    taxonomy: { id: number; handle: string; title: string; hierarchical: boolean };
    terms: TermRow[];
    locales: string[];
    blueprint: AdminTab[] | null;
}

export default function TermsPage({ taxonomy, terms, locales, blueprint }: Props) {
    const { adminPath } = usePage<SharedProps>().props;
    const main = locales[0];
    const [open, setOpen] = React.useState(false);
    const [editing, setEditing] = React.useState<TermRow | null>(null);
    const [form, setForm] = React.useState<{ parent_id: number | null; translations: Record<string, { title: string; slug: string; data: Record<string, Json> }> }>({ parent_id: null, translations: {} });

    const openCreate = () => {
        setEditing(null);
        setForm({ parent_id: null, translations: Object.fromEntries(locales.map((l) => [l, { title: '', slug: '', data: {} }])) });
        setOpen(true);
    };

    const openEdit = (term: TermRow) => {
        setEditing(term);
        setForm({
            parent_id: term.parent_id,
            translations: Object.fromEntries(
                locales.map((l) => [l, term.translations[l] ?? { title: '', slug: '', data: {} }]),
            ),
        });
        setOpen(true);
    };

    const submit = () => {
        if (editing) {
            router.put(adminUrl(`terms/${editing.id}`, adminPath), form, { preserveScroll: true, onSuccess: () => setOpen(false) });
        } else {
            router.post(adminUrl(`taxonomies/${taxonomy.handle}/terms`, adminPath), form, { preserveScroll: true, onSuccess: () => setOpen(false) });
        }
    };

    const fields = (blueprint ?? []).flatMap((t) => t.fields ?? []);

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-semibold">{taxonomy.title} — terms</h1>
                <Button onClick={openCreate}><Plus className="mr-1 h-4 w-4" /> New term</Button>
            </div>

            <ul className="flex flex-col gap-1">
                {terms.map((t) => (
                    <li key={t.id} className="flex items-center justify-between rounded-md border px-3 py-2">
                        <button type="button" className="flex items-center gap-2 text-sm" onClick={() => openEdit(t)}>
                            {t.translations[main]?.title ?? `#${t.id}`}
                            {t.parent_id && <span className="text-xs text-muted-foreground">child of #{t.parent_id}</span>}
                            <span className="text-xs text-muted-foreground">({t.count} entries)</span>
                        </button>
                        <Button
                            variant="ghost" size="icon" className="text-destructive"
                            onClick={() => window.confirm('Delete term?') && router.delete(adminUrl(`terms/${t.id}`, adminPath), { preserveScroll: true })}
                        >
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    </li>
                ))}
                {terms.length === 0 && <li className="py-8 text-center text-sm text-muted-foreground">No terms.</li>}
            </ul>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader><DialogTitle>{editing ? 'Edit term' : 'New term'}</DialogTitle></DialogHeader>
                    <div className="flex flex-col gap-4">
                        {taxonomy.hierarchical && (
                            <div className="grid gap-2">
                                <Label>Parent</Label>
                                <Select value={String(form.parent_id ?? '')} onValueChange={(v) => setForm({ ...form, parent_id: v === 'none' ? null : Number(v) })}>
                                    <SelectTrigger><SelectValue placeholder="None" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">None</SelectItem>
                                        {terms.filter((t) => t.id !== editing?.id).map((t) => (
                                            <SelectItem key={t.id} value={String(t.id)}>{t.translations[main]?.title ?? `#${t.id}`}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}
                        <Tabs defaultValue={main}>
                            <TabsList>
                                {locales.map((l) => <TabsTrigger key={l} value={l}>{l}</TabsTrigger>)}
                            </TabsList>
                            {locales.map((l) => (
                                <TabsContent key={l} value={l} className="flex flex-col gap-3 pt-3">
                                    <div className="grid gap-2">
                                        <Label>Title</Label>
                                        <Input
                                            value={form.translations[l]?.title ?? ''}
                                            onChange={(e) => setForm({
                                                ...form,
                                                translations: { ...form.translations, [l]: { ...form.translations[l], title: e.target.value } },
                                            })}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Slug</Label>
                                        <Input
                                            value={form.translations[l]?.slug ?? ''}
                                            onChange={(e) => setForm({
                                                ...form,
                                                translations: { ...form.translations, [l]: { ...form.translations[l], slug: e.target.value } },
                                            })}
                                        />
                                    </div>
                                    {fields.length > 0 && (
                                        <FieldRenderer
                                            fields={fields}
                                            values={form.translations[l]?.data ?? {}}
                                            onChange={(values) => setForm({
                                                ...form,
                                                translations: { ...form.translations, [l]: { ...form.translations[l], data: values } },
                                            })}
                                        />
                                    )}
                                </TabsContent>
                            ))}
                        </Tabs>
                        <Button onClick={submit}>Save term</Button>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    );
}
