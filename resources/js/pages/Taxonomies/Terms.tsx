import * as React from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { ExternalLink, Plus } from 'lucide-react';
import { DataTable, type FilterDef } from '@/components/data-table/DataTable';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import FieldRenderer from '@/fields/FieldRenderer';
import { SEO_FIELDS } from '@/lib/seo-fields';
import { TemplateHelp } from '@/components/app/template-help';
import { adminUrl } from '@/lib/route';
import { InputError } from '@/components/app/input-error';
import { useCan } from '@/lib/can';
import type { AdminTab, ColumnDef, Json, Paginated, SharedProps } from '@/types';

interface TermRow {
    id: number;
    parent_id: number | null;
    template?: string | null;
    translations: Record<string, { title: string; slug: string; data: Record<string, Json>; seo?: Record<string, Json> }>;
    count: number;
    url?: string | null;
}

interface Row {
    id: number;
    title: string;
    depth: number;
    slug: string;
    parent: string;
    entries: number;
    languages: string;
    created_at: string | null;
    url: string | null;
}

interface Props {
    taxonomy: { id: number; handle: string; title: string; hierarchical: boolean; template?: string | null };
    terms: TermRow[];
    columns: ColumnDef[];
    rows: Paginated<Row>;
    meta: { search: string | null; filters: Record<string, string>; sort: string | null };
    parents: { value: string; label: string }[];
    reorderable: boolean;
    locales: string[];
    mainLocale?: string;
    blueprint: AdminTab[] | null;
}

export default function TermsPage({ taxonomy, terms, columns, rows, meta, parents, reorderable, locales, mainLocale, blueprint }: Props) {
    const { adminPath } = usePage<SharedProps>().props;
    const can = useCan();
    const main = mainLocale ?? locales[0];
    const [open, setOpen] = React.useState(false);
    const [tab, setTab] = React.useState(main);
    const [errors, setErrors] = React.useState<Record<string, string>>({});
    const [processing, setProcessing] = React.useState(false);
    const [editing, setEditing] = React.useState<TermRow | null>(null);
    const [form, setForm] = React.useState<{ parent_id: number | null; template: string; translations: Record<string, { title: string; slug: string; data: Record<string, Json>; seo?: Record<string, Json> }> }>({ parent_id: null, template: '', translations: {} });

    const openCreate = () => {
        setEditing(null);
        setForm({ parent_id: null, template: '', translations: Object.fromEntries(locales.map((l) => [l, { title: '', slug: '', data: {}, seo: {} }])) });
        setErrors({});
        setTab(main);
        setOpen(true);
    };

    const openEdit = (term: TermRow) => {
        setEditing(term);
        setForm({
            parent_id: term.parent_id,
            template: term.template ?? '',
            translations: Object.fromEntries(
                locales.map((l) => [l, { seo: {}, ...(term.translations[l] ?? { title: '', slug: '', data: {} }) }]),
            ),
        });
        setErrors({});
        setTab(main);
        setOpen(true);
    };

    const submit = () => {
        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => { setOpen(false); setErrors({}); },
            onError: (e: Record<string, string>) => {
                setErrors(e);
                // Jump to the first language with a problem.
                const bad = locales.find((l) => Object.keys(e).some((k) => k.startsWith(`translations.${l}`)));
                if (bad) setTab(bad);
            },
        };
        if (editing) {
            router.put(adminUrl(`terms/${editing.id}`, adminPath), form, options);
        } else {
            router.post(adminUrl(`taxonomies/${taxonomy.handle}/terms`, adminPath), form, options);
        }
    };
    const localeHasError = (l: string) => Object.keys(errors).some((k) => k.startsWith(`translations.${l}`));

    const fields = (blueprint ?? []).flatMap((t) => t.fields ?? []);
    const canEditTerms = can(`sunrice.terms.${taxonomy.id}.edit`);

    const known = new Set(terms.map((t) => t.id));
    const childrenOf = (parent: number | null) => terms.filter((t) => (t.parent_id !== null && known.has(t.parent_id) ? t.parent_id : null) === parent);

    // The term being edited and its descendants can't be its parent.
    const excludedParents = new Set<number>();
    const exclude = (id: number) => {
        excludedParents.add(id);
        childrenOf(id).forEach((c) => exclude(c.id));
    };
    if (editing) exclude(editing.id);

    const canDelete = can(`sunrice.terms.${taxonomy.id}.delete`);
    const filters: FilterDef[] = [
        ...(taxonomy.hierarchical && parents.length > 0
            ? [{ key: 'parent', label: 'Parent', type: 'select' as const, options: [{ value: 'root', label: 'Top level only' }, ...parents] }]
            : []),
        ...(locales.length > 1
            ? [{ key: 'missing', label: 'Missing language', type: 'select' as const, options: locales.filter((l) => l !== main).map((l) => ({ value: l, label: `No ${l.toUpperCase()} version` })) }]
            : []),
    ];

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
                <h1 className="sunrice-page-title">{taxonomy.title} — terms</h1>
                {can(`sunrice.terms.${taxonomy.id}.create`) && <Button onClick={openCreate}><Plus className="mr-1 h-4 w-4" /> New term</Button>}
            </div>

            {reorderable && <p className="text-sm text-muted-foreground">Drag terms to set their order. A term's parent is set in its editor.</p>}
            <DataTable<Row>
                columns={columns}
                rows={rows}
                meta={meta}
                tableKey={`terms-${taxonomy.handle}`}
                filters={filters}
                searchPlaceholder="Search terms…"
                reorderable={reorderable}
                reorderUrl={adminUrl(`taxonomies/${taxonomy.handle}/terms/reorder`, adminPath)}
                bulkUrl={canDelete ? adminUrl(`taxonomies/${taxonomy.handle}/terms/bulk`, adminPath) : undefined}
                bulkActions={canDelete ? [{ key: 'delete', label: 'Delete', variant: 'destructive', confirm: 'Delete the selected terms? Their child terms are deleted too, and they are removed from every entry.' }] : []}
                onRowClick={canEditTerms ? (row) => { const term = terms.find((t) => t.id === row.id); if (term) openEdit(term); } : undefined}
                renderCell={(row, column) => {
                    if (column.key === 'title') {
                        return (
                            <span className="inline-flex items-center gap-2" style={{ paddingLeft: meta.sort || meta.search ? 0 : row.depth * 20 }}>
                                {row.depth > 0 && !meta.sort && !meta.search && <span className="text-muted-foreground" aria-hidden>└</span>}
                                {row.title}
                                {row.url && (
                                    <a
                                        href={row.url}
                                        target="_blank"
                                        rel="noopener"
                                        onClick={(e) => e.stopPropagation()}
                                        className="text-muted-foreground hover:text-foreground"
                                        aria-label="Visit term page"
                                        title="Visit term page"
                                    >
                                        <ExternalLink className="size-3.5" />
                                    </a>
                                )}
                            </span>
                        );
                    }
                    if (column.key === 'slug') return <code className="text-xs text-muted-foreground">{row.slug}</code>;
                    return undefined;
                }}
            />

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
                    <DialogHeader><DialogTitle>{editing ? 'Edit term' : 'New term'}</DialogTitle></DialogHeader>
                    <div className="flex flex-col gap-4">
                        {taxonomy.hierarchical && (
                            <div className="grid gap-2">
                                <Label>Parent</Label>
                                <Select value={String(form.parent_id ?? '')} onValueChange={(v) => setForm({ ...form, parent_id: v === 'none' ? null : Number(v) })}>
                                    <SelectTrigger><SelectValue placeholder="None" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">None</SelectItem>
                                        {terms.filter((t) => !excludedParents.has(t.id)).map((t) => (
                                            <SelectItem key={t.id} value={String(t.id)}>{t.translations[main]?.title ?? `#${t.id}`}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.parent_id} />
                            </div>
                        )}
                        <Tabs value={tab} onValueChange={setTab} activationMode="manual">
                            <TabsList>
                                {locales.map((l) => (
                                    <TabsTrigger
                                        key={l}
                                        value={l}
                                        title={l === main ? 'Main language' : 'Optional: leave empty to use the main language'}
                                        className={cn('gap-1.5 uppercase', localeHasError(l) && 'text-destructive')}
                                    >
                                        {l}
                                        {l !== main && (form.translations[l]?.title ?? '').trim() !== '' && (
                                            <span className="size-1.5 rounded-full bg-emerald-500" aria-label="translated" />
                                        )}
                                    </TabsTrigger>
                                ))}
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
                                        <InputError message={errors[`translations.${l}.title`] ?? errors[`translations.${l}`]} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Slug</Label>
                                        <Input
                                            value={form.translations[l]?.slug ?? ''}
                                            onChange={(e) => setForm({
                                                ...form,
                                                translations: { ...form.translations, [l]: { ...form.translations[l], slug: e.target.value } },
                                            })}
                                            placeholder="Generated from the title"
                                        />
                                        <InputError message={errors[`translations.${l}.slug`]} />
                                    </div>
                                    {fields.length > 0 && (
                                        <FieldRenderer
                                            fields={fields}
                                            values={form.translations[l]?.data ?? {}}
                                            errors={Object.fromEntries(Object.entries(errors)
                                                .filter(([k]) => k.startsWith(`translations.${l}.data.`))
                                                .map(([k, v]) => [k.replace(`translations.${l}.`, ''), v]))}
                                            onChange={(values) => setForm({
                                                ...form,
                                                translations: { ...form.translations, [l]: { ...form.translations[l], data: values } },
                                            })}
                                        />
                                    )}
                                    <details className="group rounded-lg border border-border/80">
                                        <summary className="flex cursor-pointer list-none items-center justify-between px-3 py-2 text-sm font-medium">
                                            SEO ({l.toUpperCase()})
                                            <span aria-hidden className="text-muted-foreground transition-transform group-open:rotate-90">›</span>
                                        </summary>
                                        <div className="border-t border-border/70 p-3">
                                            <FieldRenderer
                                                fields={SEO_FIELDS}
                                                values={(form.translations[l]?.seo ?? {}) as Record<string, Json>}
                                                pathPrefix="seo"
                                                errors={Object.fromEntries(Object.entries(errors)
                                                    .filter(([k]) => k.startsWith(`translations.${l}.seo.`))
                                                    .map(([k, v]) => [k.replace(`translations.${l}.`, ''), v]))}
                                                onChange={(values) => setForm({
                                                    ...form,
                                                    translations: { ...form.translations, [l]: { ...form.translations[l], seo: values } },
                                                })}
                                            />
                                        </div>
                                    </details>
                                </TabsContent>
                            ))}
                        </Tabs>
                        {fields.length === 0 && (
                            <p className="text-xs text-muted-foreground">
                                Need more on terms, such as a description or image? Pick a blueprint in{' '}
                                {can('sunrice.taxonomies.edit') ? (
                                    <Link className="underline" href={adminUrl(`structure/taxonomies/${taxonomy.id}/edit`, adminPath)}>this taxonomy's settings</Link>
                                ) : (
                                    "this taxonomy's settings"
                                )}
                                .
                            </p>
                        )}
                        <div className="grid gap-2">
                            <Label htmlFor="term-template">Template</Label>
                            <Input
                                id="term-template"
                                className="font-mono text-sm"
                                placeholder={taxonomy.template || `e.g. taxonomies.${taxonomy.handle}-featured`}
                                value={form.template}
                                onChange={(e) => setForm({ ...form, template: e.target.value })}
                            />
                            <TemplateHelp
                                example={`taxonomies.${taxonomy.handle}-featured`}
                                defaults={[...(taxonomy.template ? [taxonomy.template] : []), `sunrice.taxonomies.${taxonomy.handle}.show`, 'sunrice.taxonomies.show']}
                                lead="Overrides the taxonomy's template for this term's page."
                            />
                            <InputError message={errors.template} />
                        </div>
                        <InputError message={errors.translations} />
                        <Button onClick={submit} disabled={processing}>{processing ? 'Saving…' : 'Save term'}</Button>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    );
}
