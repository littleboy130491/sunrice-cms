import * as React from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, LoaderCircle } from 'lucide-react';
import { toast } from 'sonner';
import { KeptEditsNotice, VersionConflict, useEditLock } from '@/components/app/edit-lock';
import { RelatedMenu, type RelatedLink } from '@/components/app/related-menu';
import { useUnsavedChanges } from '@/lib/use-unsaved-changes';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { CollapsibleCard } from '@/components/app/collapsible-card';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import FieldRenderer from '@/fields/FieldRenderer';
import { adminUrl } from '@/lib/route';
import { InputError } from '@/components/app/input-error';
import type { AdminTab, Json, SharedProps } from '@/types';

interface Props {
    globalSet: { id: number; handle: string; title: string; group: string; blueprint_id: number | null; translatable: boolean } | null;
    blueprint: AdminTab[] | null;
    values: Record<string, Record<string, Json>> | null;
    /** Fingerprints of the loaded values per language: a save is refused if they changed meanwhile. */
    versions?: Record<string, string> | null;
    blueprints: { id: number; title: string }[];
    locales: string[];
    mainLocale?: string;
    related?: RelatedLink[];
}

/** Server errors for values.* are shown against the matching data.* field paths. */
const valueErrors = (errors: Record<string, string>) =>
    Object.fromEntries(Object.entries(errors).map(([k, v]) => [k.replace(/^values\./, 'data.'), v]));

/** Settings errors come back as meta.title etc. when saved with the content. */
const metaError = (errors: Record<string, string>, key: string) => errors[`meta.${key}`] ?? errors[key];

export default function GlobalForm({ globalSet, blueprint, values, versions, blueprints, locales, mainLocale = locales[0], related }: Props) {
    const { adminPath } = usePage<SharedProps>().props;
    const isNew = globalSet === null;
    const listUrl = adminUrl('globals', adminPath);
    const [errors, setErrors] = React.useState<Record<string, string>>({});
    const [processing, setProcessing] = React.useState(false);

    const savedMeta = {
        handle: globalSet?.handle ?? '',
        title: globalSet?.title ?? '',
        group: globalSet?.group ?? 'global',
        blueprint_id: globalSet?.blueprint_id ?? ('' as number | ''),
        translatable: globalSet?.translatable ?? false,
    };
    const [meta, setMeta] = React.useState(savedMeta);
    // After a save the page reloads with the stored settings: start from them again.
    const savedMetaKey = JSON.stringify(savedMeta);
    React.useEffect(() => setMeta(JSON.parse(savedMetaKey)), [savedMetaKey]);
    const metaDirty = !isNew && (meta.title !== savedMeta.title || meta.blueprint_id !== savedMeta.blueprint_id || meta.translatable !== savedMeta.translatable);

    const [locale, setLocale] = React.useState(globalSet?.translatable ? mainLocale : '_shared');
    const [data, setData] = React.useState<Record<string, Json>>({});

    // Translatable switched on/off (or the global just created): pick the right row.
    React.useEffect(() => {
        setLocale(globalSet?.translatable ? mainLocale : '_shared');
    }, [globalSet?.id, globalSet?.translatable, mainLocale]);

    // Load the language's saved values when it changes or after a save. Keyed
    // on the saved content, so a failed save (same values) keeps the edits.
    const saved = JSON.stringify(values?.[locale] ?? {});
    React.useEffect(() => {
        setData(JSON.parse(saved));
        setErrors({});
    }, [locale, saved]);

    // Only one person edits a global set (in a language) at a time.
    const lock = useEditLock({
        type: 'global',
        id: globalSet?.id,
        locale: locale === '_shared' ? '' : locale,
        enabled: !isNew,
        getUnsaved: () => (JSON.stringify(data) !== saved ? { data } : null),
        leaveTo: listUrl,
    });
    const valuesDirty = !isNew && JSON.stringify(data) !== saved;
    const dirty = !lock.readOnly && (valuesDirty || metaDirty);
    const newDirty = isNew && (meta.title !== '' || meta.blueprint_id !== '');

    const loadKept = async (id: number) => {
        const content = await lock.loadKept(id).catch(() => null);
        if (!content) return toast.error('Could not load those changes.');
        setData((content.data ?? {}) as Record<string, Json>);
        toast.success('Loaded. Review them, then save.');
    };

    // Each language is saved separately: don't drop this one's edits silently.
    const switchLocale = (lc: string) => {
        if (lc === locale) return;
        if (valuesDirty && !window.confirm(`You have unsaved changes in ${locale.toUpperCase()}. Switch language and discard them?`)) return;
        setLocale(lc);
    };

    /** One Save: the values of this language, plus the settings when they changed. */
    const save = (overwrite = false) => {
        if (lock.readOnly || processing) return;
        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => setErrors({}),
            onError: (e: Record<string, string>) => setErrors(e),
        };
        if (isNew) {
            router.post(listUrl, meta, options);
            return;
        }
        if (!dirty && !overwrite) return;
        router.put(adminUrl(`globals/${globalSet.id}`, adminPath), {
            locale: locale === '_shared' ? null : locale,
            values: data,
            version: versions?.[locale] ?? '',
            overwrite,
            ...(metaDirty ? { meta: { title: meta.title, blueprint_id: meta.blueprint_id, translatable: meta.translatable } } : {}),
        }, options);
    };
    useUnsavedChanges((dirty || newDirty) && !processing, () => save());

    const fields = (blueprint ?? []).flatMap((t) => t.fields ?? []);
    const hasErrors = Object.keys(errors).length > 0;

    const blueprintSelect = (
        <div className="grid gap-2">
            <Label htmlFor="global-blueprint">Blueprint</Label>
            <Select value={String(meta.blueprint_id || '')} onValueChange={(v) => setMeta({ ...meta, blueprint_id: Number(v) })}>
                <SelectTrigger id="global-blueprint" className="w-full"><SelectValue placeholder="Choose…" /></SelectTrigger>
                <SelectContent>
                    {blueprints.map((b) => <SelectItem key={b.id} value={String(b.id)}>{b.title}</SelectItem>)}
                </SelectContent>
            </Select>
            <InputError message={metaError(errors, 'blueprint_id')} />
        </div>
    );
    const translatableBox = (
        <label className="flex items-start gap-2 text-sm">
            <Checkbox className="mt-0.5" checked={meta.translatable} onCheckedChange={(c) => setMeta({ ...meta, translatable: !!c })} />
            <span>
                Translatable
                <span className="block text-xs text-muted-foreground">Each language has its own values.</span>
            </span>
        </label>
    );

    return (
        <div className="flex flex-col gap-6">
            {/* Header: back, title, then the page's actions. */}
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="flex min-w-0 items-start gap-3">
                    <Button variant="outline" size="icon" className="size-8 shrink-0" asChild>
                        <Link href={listUrl} aria-label="Back to globals"><ArrowLeft /></Link>
                    </Button>
                    <div className="min-w-0 space-y-1">
                        <h1 className="truncate sunrice-page-title">{isNew ? 'New global set' : meta.title || globalSet.title}</h1>
                        <p className="text-sm text-muted-foreground">Globals{!isNew && meta.group === 'template_part' ? ' · Template part' : ''}</p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    {!lock.readOnly && (
                        <Button type="button" onClick={() => save()} disabled={processing || (!isNew && !dirty)} variant={isNew || dirty ? 'default' : 'outline'}>
                            {processing && <LoaderCircle className="animate-spin" />}
                            {isNew ? 'Create' : dirty ? 'Save' : 'Saved'}
                        </Button>
                    )}
                    <RelatedMenu links={related} />
                </div>
            </div>

            {!isNew && globalSet.translatable && (
                <Tabs value={locale} onValueChange={switchLocale} activationMode="manual">
                    <TabsList>
                        {locales.map((l) => <TabsTrigger key={l} value={l} className="uppercase">{l}</TabsTrigger>)}
                    </TabsList>
                </Tabs>
            )}

            {!isNew && (
                <>
                    {lock.dialogs}
                    <VersionConflict message={errors.version} onOverwrite={() => save(true)} />
                    {!lock.readOnly && <KeptEditsNotice kept={lock.kept} onLoad={loadKept} onDiscard={lock.discardKept} />}
                    {lock.readOnly && (
                        <p className="rounded-md border border-dashed bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
                            {lock.viewingBy ?? 'Someone else'} is editing this. You&apos;re viewing it read-only.
                        </p>
                    )}
                </>
            )}

            {/* Content on the left; settings beside it, below it on narrow screens. */}
            <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
                {isNew ? (
                    <CollapsibleCard title="Details" storageKey="global:details" hasErrors={hasErrors} contentClassName="flex flex-col gap-4">
                        <div className="grid items-start gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="global-title">Title</Label>
                                <Input id="global-title" value={meta.title} autoFocus onChange={(e) => setMeta({
                                    ...meta,
                                    title: e.target.value,
                                    handle: e.target.value.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, ''),
                                })} />
                                <InputError message={errors.title} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="global-handle">Handle</Label>
                                <Input id="global-handle" className="font-mono" value={meta.handle} onChange={(e) => setMeta({ ...meta, handle: e.target.value })} />
                                <InputError message={errors.handle} />
                            </div>
                        </div>
                        <div className="grid gap-2">
                            <Label>Group</Label>
                            <Select value={meta.group} onValueChange={(v) => setMeta({ ...meta, group: v })}>
                                <SelectTrigger className="w-48"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="global">Global</SelectItem>
                                    <SelectItem value="template_part">Template part</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="max-w-sm">{blueprintSelect}</div>
                        {translatableBox}
                    </CollapsibleCard>
                ) : (
                    <CollapsibleCard title="Content" storageKey="global:content-open" hasErrors={Object.keys(valueErrors(errors)).some((k) => k.startsWith('data.'))}>
                        <fieldset disabled={lock.readOnly} className="min-w-0">
                            {fields.length > 0 ? (
                                <FieldRenderer fields={fields} values={data} errors={valueErrors(errors)} onChange={setData} />
                            ) : (
                                <p className="text-sm text-muted-foreground">This blueprint has no fields yet. Add some to the blueprint, then fill them in here.</p>
                            )}
                        </fieldset>
                    </CollapsibleCard>
                )}

                <div className="flex flex-col gap-4">
                    {!isNew && (
                        <CollapsibleCard
                            title="Settings"
                            titleClassName="text-sm"
                            storageKey="global:settings-side"
                            hasErrors={['title', 'blueprint_id'].some((k) => metaError(errors, k))}
                            contentClassName="flex flex-col gap-4"
                        >
                            <fieldset disabled={lock.readOnly} className="flex min-w-0 flex-col gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="global-title">Title</Label>
                                    <Input id="global-title" value={meta.title} onChange={(e) => setMeta({ ...meta, title: e.target.value })} />
                                    <InputError message={metaError(errors, 'title')} />
                                </div>
                                {blueprintSelect}
                                {translatableBox}
                                <p className="text-xs text-muted-foreground">
                                    Handle <code>{globalSet.handle}</code>, used in templates: <code>sunrice_global(&apos;{globalSet.handle}&apos;)</code>.
                                </p>
                            </fieldset>
                        </CollapsibleCard>
                    )}
                    {!lock.readOnly && (
                        <p className="text-center text-xs text-muted-foreground">
                            {dirty ? (
                                <span className="inline-flex items-center gap-1.5"><span className="size-1.5 rounded-full bg-amber-500" /> Unsaved changes</span>
                            ) : !isNew && 'All changes saved'}
                            <span className="hidden sm:inline"> · Ctrl/⌘ S</span>
                        </p>
                    )}
                </div>
            </div>
        </div>
    );
}
