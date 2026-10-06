import * as React from 'react';
import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import FieldRenderer from '@/fields/FieldRenderer';
import { adminUrl } from '@/lib/route';
import type { AdminTab, SharedProps } from '@/types'; import type { Json } from '@/types';

interface Props {
    globalSet: { id: number; handle: string; title: string; group: string; blueprint_id: number | null; translatable: boolean } | null;
    blueprint: AdminTab[] | null;
    values: Record<string, Record<string, Json>> | null;
    blueprints: { id: number; title: string }[];
    locales: string[];
}

export default function GlobalForm({ globalSet, blueprint, values, blueprints, locales }: Props) {
    const { adminPath } = usePage<SharedProps>().props;
    const isNew = globalSet === null;
    const mainLocale = locales[0];

    const [meta, setMeta] = React.useState({
        handle: globalSet?.handle ?? '',
        title: globalSet?.title ?? '',
        group: globalSet?.group ?? 'global',
        blueprint_id: globalSet?.blueprint_id ?? '',
        translatable: globalSet?.translatable ?? false,
    });
    const [locale, setLocale] = React.useState(globalSet?.translatable ? mainLocale : '_shared');
    const [data, setData] = React.useState<Record<string, Json>>({});

    React.useEffect(() => {
        setData(values?.[locale] ?? {});
    }, [locale, values]);

    const submitMeta = () => {
        if (isNew) {
            router.post(adminUrl('globals', adminPath), meta);
        } else {
            router.put(adminUrl(`globals/${globalSet.id}`, adminPath), {
                locale: locale === '_shared' ? null : locale,
                values: data,
            }, { preserveScroll: true });
        }
    };

    const fields = (blueprint ?? []).flatMap((t) => t.fields ?? []);

    return (
        <div className="flex max-w-3xl flex-col gap-4">
            <h1 className="text-xl font-semibold tracking-tight">{isNew ? 'New global' : `Edit ${globalSet.title}`}</h1>

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
                            </div>
                            <div className="grid gap-2">
                                <Label>Handle</Label>
                                <Input value={meta.handle} onChange={(e) => setMeta({ ...meta, handle: e.target.value })} />
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
                        </div>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox checked={meta.translatable} onCheckedChange={(c) => setMeta({ ...meta, translatable: !!c })} />
                            Translatable
                        </label>
                        <Button onClick={submitMeta} className="w-32">Create</Button>
                    </CardContent>
                </Card>
            ) : (
                <>
                    {globalSet.translatable && (
                        <Tabs value={locale} onValueChange={setLocale}>
                            <TabsList>
                                {locales.map((l) => <TabsTrigger key={l} value={l}>{l}</TabsTrigger>)}
                            </TabsList>
                        </Tabs>
                    )}
                    <Card>
                        <CardContent>
                            <FieldRenderer fields={fields} values={data} onChange={setData} />
                        </CardContent>
                    </Card>
                    <Button onClick={submitMeta} className="w-32">Save</Button>
                </>
            )}
        </div>
    );
}
