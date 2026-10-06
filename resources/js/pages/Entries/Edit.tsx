import * as React from 'react';
import { useForm, usePage, router, Link } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, Copy, EyeOff, History, Languages, LoaderCircle, MoreHorizontal, Send, Trash2, Undo2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { InputError } from '@/components/app/input-error';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import FieldRenderer from '@/fields/FieldRenderer';
import { TranslationModeProvider } from '@/fields/translation-mode';
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
    mainLocale?: string;
}

function flatFields(tabs: AdminTab[] | null): AdminField[] {
    return (tabs ?? []).flatMap((t) => t.fields ?? []);
}

export default function EntryEdit({ collection, entry, blueprint, blueprints, taxonomies, locales, mainLocale = locales[0] }: Props) {
    const { adminPath } = usePage<SharedProps>().props;
    const can = useCan();
    const isNew = entry === null;

    const [locale, setLocale] = React.useState(mainLocale);
    const existing = entry?.translations ?? {};

    // Working data for each locale is the draft (data.draft) if present
    // else the live columns. A language without a translation yet starts
    // from the main language's text, laid out exactly like it.
    const initial = (lc: string): TranslationState => existing[lc] ?? {
        title: lc === mainLocale ? '' : existing[mainLocale]?.title ?? '',
        slug: '',
        data: lc === mainLocale ? {} : existing[mainLocale]?.data ?? {},
        seo: lc === mainLocale ? {} : existing[mainLocale]?.seo ?? {},
        is_ready: false, is_outdated: false,
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

    const statusLabel = entry?.status === 'published' ? 'Published' : 'Draft';

    useBreadcrumbs([
        { label: 'Content' },
        { label: collection.title, href: adminUrl(`collections/${collection.handle}/entries`, adminPath) },
        { label: isNew ? 'New entry' : existing[mainLocale]?.title || 'Untitled' },
    ]);

    return (
        <div className="flex flex-col gap-6">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="flex min-w-0 items-start gap-3">
                    <Button variant="outline" size="icon" className="size-8 shrink-0" asChild>
                        <Link href={adminUrl(`collections/${collection.handle}/entries`, adminPath)} aria-label={`Back to ${collection.title}`}>
                            <ArrowLeft />
                        </Link>
                    </Button>
                    <div className="min-w-0 space-y-1">
                        <h1 className="truncate text-xl font-semibold tracking-tight">
                            {isNew ? `New ${collection.title} entry` : form.data.title || 'Untitled'}
                        </h1>
                        <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <span>{collection.title}</span>
                            {entry && (
                                <Badge variant={entry.status === 'published' ? 'success' : 'secondary'}>{statusLabel}</Badge>
                            )}
                            {current.is_outdated && <Badge variant="warning">Outdated translation</Badge>}
                            {current.has_draft && entry?.status === 'published' && <Badge variant="outline">Unpublished changes</Badge>}
                        </div>
                    </div>
                </div>
                {!isNew && (
                    <div className="flex items-center gap-2">
                        <Button type="button" onClick={publish}>
                            <Send /> Publish
                        </Button>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button type="button" variant="outline" size="icon" aria-label="More actions">
                                    <MoreHorizontal />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-48">
                                <DropdownMenuItem onSelect={markReady}>
                                    <CheckCircle2 /> {current.is_ready ? 'Unmark ready' : 'Mark ready'}
                                </DropdownMenuItem>
                                {locale !== mainLocale && current.is_ready && (
                                    <DropdownMenuItem onSelect={returnToDraft}>
                                        <Undo2 /> Return to draft
                                    </DropdownMenuItem>
                                )}
                                {entry?.status === 'published' && (
                                    <DropdownMenuItem onSelect={unpublish}>
                                        <EyeOff /> Unpublish
                                    </DropdownMenuItem>
                                )}
                                <DropdownMenuItem onSelect={duplicate}>
                                    <Copy /> Duplicate
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem variant="destructive" onSelect={trash}>
                                    <Trash2 /> Move to trash
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                )}
            </div>

            {locales.length > 1 && (
                <Tabs value={locale} onValueChange={switchLocale}>
                    <TabsList>
                        {locales.map((lc) => (
                            <TabsTrigger key={lc} value={lc} className="gap-1.5 uppercase">
                                {lc}
                                {existing[lc]?.has_draft && <span className="size-1.5 rounded-full bg-amber-500" aria-label="has draft" />}
                                {lc !== mainLocale && existing[lc]?.is_ready && <span className="size-1.5 rounded-full bg-emerald-500" aria-label="ready" />}
                            </TabsTrigger>
                        ))}
                    </TabsList>
                </Tabs>
            )}

            <form onSubmit={submit} className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
                <div className="flex min-w-0 flex-col gap-6">
                    {contentTabs.map((tab, index) => (
                        <Card key={tab.handle}>
                            <CardHeader>
                                <CardTitle>{tab.label}</CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-6">
                                {index === 0 && (
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="entry-title">Title</Label>
                                            <Input id="entry-title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} required />
                                            <InputError message={form.errors.title} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="entry-slug">Slug</Label>
                                            <Input
                                                id="entry-slug"
                                                className="font-mono text-sm"
                                                value={form.data.slug}
                                                onChange={(e) => form.setData('slug', e.target.value)}
                                                placeholder="Generated from the title"
                                            />
                                            <InputError message={form.errors.slug} />
                                        </div>
                                    </div>
                                )}
                                {index === 0 && locale !== mainLocale && (
                                    <p className="flex items-start gap-2 rounded-md border border-dashed bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
                                        <Languages className="mt-0.5 size-4 shrink-0" />
                                        <span>
                                            Translate the text here. Rows, blocks, their order and visibility, images and other shared
                                            fields come from the {mainLocale.toUpperCase()} version. Text left unchanged keeps following it.
                                        </span>
                                    </p>
                                )}
                                <TranslationModeProvider secondary={locale !== mainLocale} mainLocale={mainLocale}>
                                    <FieldRenderer
                                        fields={tab.fields}
                                        values={form.data.data}
                                        errors={form.errors}
                                        onChange={(values) => form.setData('data', values)}
                                    />
                                </TranslationModeProvider>
                            </CardContent>
                        </Card>
                    ))}
                    {seoTab && (
                        <Card>
                            <CardHeader>
                                <CardTitle>{seoTab.label}</CardTitle>
                                <CardDescription>How this entry appears in search results and social shares.</CardDescription>
                            </CardHeader>
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

                <div className="flex flex-col gap-6 lg:sticky lg:top-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">Status</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            {entry && (
                                <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                                    <dt className="text-muted-foreground">Visibility</dt>
                                    <dd className="text-right">{statusLabel}</dd>
                                    {entry.published_at && (
                                        <>
                                            <dt className="text-muted-foreground">Published</dt>
                                            <dd className="text-right">{entry.published_at}</dd>
                                        </>
                                    )}
                                    {locale !== mainLocale && (
                                        <>
                                            <dt className="text-muted-foreground">Translation</dt>
                                            <dd className="text-right">{current.is_ready ? 'Ready' : 'Draft'}</dd>
                                        </>
                                    )}
                                </dl>
                            )}
                            {isNew && blueprints.length > 1 && (
                                <div className="grid gap-2">
                                    <Label>Blueprint</Label>
                                    <Select
                                        value={String(form.data.blueprint_id || '')}
                                        onValueChange={(v) => form.setData('blueprint_id', Number(v))}
                                    >
                                        <SelectTrigger className="w-full"><SelectValue placeholder="Collection default" /></SelectTrigger>
                                        <SelectContent>
                                            {blueprints.map((b) => <SelectItem key={b.id} value={String(b.id)}>{b.title}</SelectItem>)}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}
                            <Button type="submit" variant={isNew ? 'default' : 'outline'} disabled={form.processing} className="w-full">
                                {form.processing && <LoaderCircle className="animate-spin" />}
                                {isNew ? 'Create draft' : 'Save draft'}
                            </Button>
                        </CardContent>
                    </Card>

                    {!isNew && current.revisions.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-sm">
                                    <History className="size-4" /> Revisions
                                </CardTitle>
                                <CardDescription>Restoring copies a revision into the draft.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <ul className="-mx-2 flex flex-col">
                                    {current.revisions.slice(0, 10).map((r) => (
                                        <li key={r.id} className="flex items-center justify-between rounded-md px-2 py-1 text-sm hover:bg-accent">
                                            <span className="text-muted-foreground">{r.created_at}</span>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                className="h-7"
                                                onClick={() => router.post(adminUrl(`revisions/${r.id}/restore`, adminPath), {}, { preserveScroll: true })}
                                            >
                                                Restore
                                            </Button>
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
