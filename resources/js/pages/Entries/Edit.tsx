import * as React from 'react';
import { useForm, usePage, router, Link } from '@inertiajs/react';
import { ArrowLeft, Copy, History, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import FieldRenderer from '@/fields/FieldRenderer';
import { adminUrl } from '@/lib/route';
import { useCan } from '@/lib/can';
import type { AdminField, AdminTab, SharedProps } from '@/types'; import type { Json } from '@/types';

interface RevisionRow { id: number; created_at: string | null; user_id: number | null }

interface TranslationState {
    id?: number;
    title: string;
    slug: string;
    data: Record<string, Json>;
    seo: Record<string, Json>;
    is_ready: boolean;
    is_outdated: boolean;
    has_draft: boolean;
    draft_title: string;
    draft_slug: string;
    revisions: RevisionRow[];
}

interface Props {
    collection: { id: number; handle: string; title: string };
    entry: {
        id: number; status: string; published_at: string | null; blueprint_id: number | null;
        author_id: number | null; term_ids: number[]; translations: Record<string, TranslationState>;
    } | null;
    blueprint: AdminTab[] | null;
    blueprints: { id: number; title: string }[];
    taxonomies: { id: number; handle: string; title: string }[];
    locales: string[];
}

function flatFields(tabs: AdminTab[] | null): AdminField[] {
    return (tabs ?? []).flatMap((t) => t.fields ?? []);
}

export default function EntryEdit({ collection, entry, blueprint, blueprints, taxonomies, locales }: Props) {
    const { adminPath } = usePage<SharedProps>().props;
    const can = useCan();
    const mainLocale = locales[0];
    const isNew = entry === null;

    const [locale, setLocale] = React.useState(mainLocale);
    const existing = entry?.translations ?? {};

    // Working data for each locale is the draft (data.draft) if present
    // else the live columns.
    const initial = (lc: string): TranslationState => existing[lc] ?? {
        title: '', slug: '', data: {}, seo: {}, is_ready: false, is_outdated: false,
        has_draft: false, draft_title: '', draft_slug: '', revisions: [],
    };

    const form = useForm<{
        locale: string;
        title: string;
        slug: string;
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        data: any;
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        seo: any;
        is_ready?: boolean;
        blueprint_id?: number | string;
    }>({
        blueprint_id: '',
        locale: mainLocale,
        title: initial(mainLocale).title,
        slug: initial(mainLocale).slug,
        data: initial(mainLocale).data,
        seo: initial(mainLocale).seo,
    });

    const switchLocale = (lc: string) => {
        setLocale(lc);
        const t = initial(lc);
        form.setData({ locale: lc, title: t.title, slug: t.slug, data: t.data, seo: t.seo });
        form.clearErrors();
    };

    const tabs: AdminTab[] = blueprint ?? [];
    const seoTab = tabs.find((t) => t.handle === 'seo');
    const contentTabs = tabs.filter((t) => t.handle !== 'seo');
    const current = initial(locale);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isNew) {
            router.post(adminUrl(`collections/${collection.handle}/entries`, adminPath), {
                title: form.data.title,
                slug: form.data.slug,
                data: form.data.data,
                seo: form.data.seo,
                blueprint_id: form.data.blueprint_id || null,
            });
            return;
        }
        form.put(adminUrl(`entries/${entry.id}`, adminPath));
    };

    const publish = () => {
        if (!entry) return;
        router.post(adminUrl(`entries/${entry.id}/publish`, adminPath), { locale }, { preserveScroll: true });
    };

    const unpublish = () => entry && router.post(adminUrl(`entries/${entry.id}/unpublish`, adminPath), {}, { preserveScroll: true });
    const duplicate = () => entry && router.post(adminUrl(`entries/${entry.id}/duplicate`, adminPath), {}, { preserveScroll: true });
    const trash = () => entry && window.confirm('Move this entry to trash?') && router.delete(adminUrl(`entries/${entry.id}`, adminPath));
    const returnToDraft = () => {
        const tid = existing[locale]?.id;
        if (tid) router.put(adminUrl(`entry-translations/${tid}/return-to-draft`, adminPath), {}, { preserveScroll: true });
    };

    const markReady = () => {
        if (!entry) return;
        form.transform((data) => ({ ...data, is_ready: !current.is_ready }));
        form.put(adminUrl(`entries/${entry.id}`, adminPath), {
            preserveScroll: true,
            onFinish: () => form.transform((data) => data),
        });
    };

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href={adminUrl(`collections/${collection.handle}/entries`, adminPath)}><ArrowLeft className="h-4 w-4" /></Link>
                    </Button>
                    <h1 className="text-xl font-semibold">
                        {isNew ? `New ${collection.title} entry` : form.data.title || 'Untitled'}
                    </h1>
                    {entry && <Badge variant={entry.status === 'published' ? 'success' : 'secondary'}>{entry.status}</Badge>}
                    {current.is_outdated && <Badge variant="warning">unpublished changes</Badge>}
                </div>
                <div className="flex items-center gap-2">
                    {!isNew && (
                        <>
                            <Button type="button" variant="outline" size="sm" onClick={markReady}>
                                {current.is_ready ? 'Unmark ready' : 'Mark ready'}
                            </Button>
                            {locale !== mainLocale && current.is_ready && (
                                <Button type="button" variant="outline" size="sm" onClick={returnToDraft}>
                                    Return to draft
                                </Button>
                            )}
                            <Button type="button" variant="outline" size="sm" onClick={publish}>
                                Publish
                            </Button>
                            <Button type="button" variant="outline" size="sm" onClick={unpublish}>Unpublish</Button>
                            <Button type="button" variant="outline" size="sm" onClick={duplicate}><Copy className="h-4 w-4" /></Button>
                            <Button type="button" variant="outline" size="sm" className="text-destructive" onClick={trash}><Trash2 className="h-4 w-4" /></Button>
                        </>
                    )}
                </div>
            </div>

            <div className="flex items-center gap-2">
                <Label className="text-sm">Locale:</Label>
                {locales.map((lc) => (
                    <Button
                        key={lc}
                        type="button"
                        size="sm"
                        variant={locale === lc ? 'default' : 'outline'}
                        onClick={() => switchLocale(lc)}
                    >
                        {lc}
                        {existing[lc]?.has_draft && <span className="ml-1 inline-block h-1.5 w-1.5 rounded-full bg-amber-400" />}
                    </Button>
                ))}
            </div>

            <form onSubmit={submit} className="grid gap-4 lg:grid-cols-[1fr_280px]">
                <div className="flex flex-col gap-4">
                    {contentTabs.map((tab) => (
                        <Card key={tab.handle}>
                            <CardHeader><CardTitle>{tab.label}</CardTitle></CardHeader>
                            <CardContent>
                                <div className="mb-4 grid grid-cols-2 gap-4">
                                    <div className="grid gap-2">
                                        <Label>Title</Label>
                                        <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} required />
                                        {form.errors.title && <p className="text-sm text-destructive">{form.errors.title}</p>}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Slug</Label>
                                        <Input value={form.data.slug} onChange={(e) => form.setData('slug', e.target.value)} placeholder="auto" />
                                        {form.errors.slug && <p className="text-sm text-destructive">{form.errors.slug}</p>}
                                    </div>
                                </div>
                                <FieldRenderer
                                    fields={tab.fields}
                                    values={form.data.data}
                                    errors={form.errors}
                                    onChange={(values) => form.setData('data', values)}
                                />
                            </CardContent>
                        </Card>
                    ))}
                    {seoTab && (
                        <Card>
                            <CardHeader><CardTitle>{seoTab.label}</CardTitle></CardHeader>
                            <CardContent>
                                <FieldRenderer
                                    fields={seoTab.fields}
                                    values={form.data.seo}
                                    errors={form.errors}
                                    onChange={(values) => form.setData('seo', values)}
                                />
                            </CardContent>
                        </Card>
                    )}
                </div>

                <div className="flex flex-col gap-4">
                    <Card>
                        <CardContent className="flex flex-col gap-3 pt-6">
                            <Button type="submit" disabled={form.processing}>Save draft</Button>
                            {isNew && blueprints.length > 1 && (
                                <div className="grid gap-2">
                                    <Label>Blueprint</Label>
                                    <Select
                                        value={String(form.data.blueprint_id || '')}
                                        onValueChange={(v) => form.setData('blueprint_id', Number(v))}
                                    >
                                        <SelectTrigger><SelectValue placeholder="Collection default" /></SelectTrigger>
                                        <SelectContent>
                                            {blueprints.map((b) => <SelectItem key={b.id} value={String(b.id)}>{b.title}</SelectItem>)}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {!isNew && current.revisions.length > 0 && (
                        <Card>
                            <CardHeader><CardTitle className="flex items-center gap-2 text-sm"><History className="h-4 w-4" /> Revisions</CardTitle></CardHeader>
                            <CardContent>
                                <ul className="flex flex-col gap-1 text-sm">
                                    {current.revisions.slice(0, 10).map((r) => (
                                        <li key={r.id} className="flex items-center justify-between">
                                            <span>{r.created_at}</span>
                                            <Button
                                                type="button" variant="link" size="sm"
                                                onClick={() => router.post(adminUrl(`revisions/${r.id}/restore`, adminPath), {}, { preserveScroll: true })}
                                            >Restore</Button>
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </form>
        </div>
    );
}
