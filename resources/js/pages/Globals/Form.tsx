import * as React from 'react';
import { router, usePage } from '@inertiajs/react';
import { useUnsavedChanges } from '@/lib/use-unsaved-changes';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import FieldRenderer from '@/fields/FieldRenderer';
import { adminUrl } from '@/lib/route';
import { InputError } from '@/components/app/input-error';
import type { AdminTab, Json, SharedProps } from '@/types';

interface Props {
    globalSet: { id: number; handle: string; title: string; group: string; blueprint_id: number | null; translatable: boolean } | null;
    blueprint: AdminTab[] | null;
    values: Record<string, Record<string, Json>> | null;
    blueprints: { id: number; title: string }[];
    locales: string[];
    mainLocale?: string;
}

/** Server errors for values.* are shown against the matching data.* field paths. */
const valueErrors = (errors: Record<string, string>) =>
    Object.fromEntries(Object.entries(errors).map(([k, v]) => [k.replace(/^values\./, 'data.'), v]));

export default function GlobalForm({ globalSet, blueprint, values, blueprints, locales, mainLocale = locales[0] }: Props) {
    const { adminPath } = usePage<SharedProps>().props;
    const isNew = globalSet === null;
    const [errors, setErrors] = React.useState<Record<string, string>>({});
    const [processing, setProcessing] = React.useState(false);

    const [meta, setMeta] = React.useState({
        handle: globalSet?.handle ?? '',
        title: globalSet?.title ?? '',
        group: globalSet?.group ?? 'global',
        blueprint_id: globalSet?.blueprint_id ?? '',
        translatable: globalSet?.translatable ?? false,
    });
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

    const dirty = !isNew && JSON.stringify(data) !== saved;
    useUnsavedChanges(dirty && !processing, () => submitMeta());

    // Each language is saved separately: don't drop this one's edits silently.
    const switchLocale = (lc: string) => {
        if (lc === locale) return;
        if (dirty && !window.confirm(`You have unsaved changes in ${locale.toUpperCase()}. Switch language and discard them?`)) return;
        setLocale(lc);
    };

    const submitMeta = () => {
        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => setErrors({}),
            onError: (e: Record<string, string>) => setErrors(e),
        };
        if (isNew) {
            router.post(adminUrl('globals', adminPath), meta, options);
        } else {
            router.put(adminUrl(`globals/${globalSet.id}`, adminPath), {
                locale: locale === '_shared' ? null : locale,
                values: data,
            }, options);
        }
    };

    const fields = (blueprint ?? []).flatMap((t) => t.fields ?? []);

    return (
        <div className="flex max-w-3xl flex-col gap-4">
            <h1 className="sunrice-page-title">{isNew ? 'New global' : `Edit ${globalSet.title}`}</h1>

            {isNew ? (
                <Card>
                    <CardContent className="flex flex-col gap-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label>Title</Label>
                                <Input value={meta.title} onChange={(e) => setMeta({
                                    ...meta,
                                    title: e.target.value,
                                    handle: e.target.value.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, ''),
                                })} />
                                <InputError message={errors.title} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Handle</Label>
                                <Input value={meta.handle} onChange={(e) => setMeta({ ...meta, handle: e.target.value })} />
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
                        <div className="grid gap-2">
                            <Label>Blueprint</Label>
                            <Select value={String(meta.blueprint_id || '')} onValueChange={(v) => setMeta({ ...meta, blueprint_id: Number(v) })}>
                                <SelectTrigger className="w-64"><SelectValue placeholder="Choose…" /></SelectTrigger>
                                <SelectContent>
                                    {blueprints.map((b) => <SelectItem key={b.id} value={String(b.id)}>{b.title}</SelectItem>)}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.blueprint_id} />
                        </div>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox checked={meta.translatable} onCheckedChange={(c) => setMeta({ ...meta, translatable: !!c })} />
                            Translatable
                        </label>
                        <Button onClick={submitMeta} className="w-32" disabled={processing}>Create</Button>
                    </CardContent>
                </Card>
            ) : (
                <>
                    {globalSet.translatable && (
                        <Tabs value={locale} onValueChange={switchLocale} activationMode="manual">
                            <TabsList>
                                {locales.map((l) => <TabsTrigger key={l} value={l} className="uppercase">{l}</TabsTrigger>)}
                            </TabsList>
                        </Tabs>
                    )}
                    <Card>
                        <CardContent>
                            <FieldRenderer fields={fields} values={data} errors={valueErrors(errors)} onChange={setData} />
                        </CardContent>
                    </Card>
                    <Button onClick={submitMeta} className="w-32" disabled={processing}>{processing ? 'Saving…' : 'Save'}</Button>

                    <details className="rounded-md border p-4">
                        <summary className="cursor-pointer text-sm font-medium">Global settings</summary>
                        <div className="mt-4 flex flex-col gap-4">
                            <div className="grid gap-2">
                                <Label>Title</Label>
                                <Input value={meta.title} onChange={(e) => setMeta({ ...meta, title: e.target.value })} />
                                <InputError message={errors.title} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Blueprint</Label>
                                <Select value={String(meta.blueprint_id || '')} onValueChange={(v) => setMeta({ ...meta, blueprint_id: Number(v) })}>
                                    <SelectTrigger className="w-64"><SelectValue placeholder="Choose…" /></SelectTrigger>
                                    <SelectContent>
                                        {blueprints.map((b) => <SelectItem key={b.id} value={String(b.id)}>{b.title}</SelectItem>)}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.blueprint_id} />
                            </div>
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox checked={meta.translatable} onCheckedChange={(c) => setMeta({ ...meta, translatable: !!c })} />
                                Translatable
                            </label>
                            <Button
                                variant="outline"
                                className="w-40"
                                disabled={processing}
                                onClick={() => router.put(adminUrl(`globals/${globalSet.id}/meta`, adminPath), {
                                    title: meta.title, blueprint_id: meta.blueprint_id, translatable: meta.translatable,
                                }, {
                                    preserveScroll: true,
                                    onStart: () => setProcessing(true),
                                    onFinish: () => setProcessing(false),
                                    onSuccess: () => setErrors({}),
                                    onError: (e) => setErrors(e),
                                })}
                            >
                                Save settings
                            </Button>
                        </div>
                    </details>
                </>
            )}
        </div>
    );
}
