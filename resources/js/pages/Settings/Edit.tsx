import * as React from 'react';
import { useForm } from '@inertiajs/react';
import { Image as ImageIcon, LoaderCircle, Plus, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import AssetPicker, { PickedAsset } from '@/components/AssetPicker';
import EntryPicker, { PickedEntry } from '@/components/EntryPicker';
import { InputError } from '@/components/app/input-error';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import { adminUrl } from '@/lib/route';
import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types';

interface Settings {
    name: string;
    description: string | null;
    timezone: string;
    locales: { main: string; available: string[]; names: Record<string, string> };
    seo: { noindex: boolean; twitter_site: string | null; image: number | null };
    code: { head: string | null; body_start: string | null; body_end: string | null };
}

interface Props {
    settings: Settings;
    homepage: PickedEntry | null;
    shareImage: { id: number; url: string; filename: string } | null;
    timezones: string[];
    mainLocked: boolean;
}

export default function SettingsEdit({ settings, homepage, shareImage, timezones, mainLocked }: Props) {
    const { adminPath } = usePage<SharedProps>().props;
    const [home, setHome] = React.useState<PickedEntry[]>(homepage ? [homepage] : []);
    const [image, setImage] = React.useState(shareImage);
    const [newLocale, setNewLocale] = React.useState('');

    const form = useForm({
        name: settings.name ?? '',
        description: settings.description ?? '',
        timezone: settings.timezone ?? 'UTC',
        homepage_entry_id: homepage?.id ?? null as number | null,
        locales: {
            main: settings.locales.main,
            available: settings.locales.available,
            names: settings.locales.names ?? {},
        },
        seo: {
            noindex: !!settings.seo.noindex,
            twitter_site: settings.seo.twitter_site ?? '',
            image: settings.seo.image ?? null as number | null,
        },
        code: {
            head: settings.code.head ?? '',
            body_start: settings.code.body_start ?? '',
            body_end: settings.code.body_end ?? '',
        },
    });
    const errors = form.errors as Record<string, string>;
    const locales = form.data.locales;

    useBreadcrumbs([{ label: 'Settings' }]);

    const setLocales = (next: Partial<typeof locales>) => form.setData('locales', { ...locales, ...next });

    const addLocale = () => {
        const code = newLocale.trim();
        if (code === '' || locales.available.includes(code)) return;
        setLocales({ available: [...locales.available, code] });
        setNewLocale('');
    };

    const removeLocale = (code: string) => {
        const names = { ...locales.names };
        delete names[code];
        setLocales({ available: locales.available.filter((c) => c !== code), names });
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(adminUrl('settings', adminPath), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="flex max-w-3xl flex-col gap-6">
            <div className="flex items-center justify-between">
                <h1 className="text-xl font-semibold tracking-tight">Settings</h1>
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <LoaderCircle className="animate-spin" />} Save settings
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>General</CardTitle>
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="site-name">Site name</Label>
                        <Input id="site-name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="site-description">Description</Label>
                        <Textarea id="site-description" rows={2} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                        <p className="text-xs text-muted-foreground">Used as the meta description of pages that don't set their own.</p>
                        <InputError message={errors.description} />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Timezone</Label>
                            <Select value={form.data.timezone} onValueChange={(v) => form.setData('timezone', v)}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent className="max-h-72">
                                    {timezones.map((tz) => <SelectItem key={tz} value={tz}>{tz}</SelectItem>)}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.timezone} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Homepage</Label>
                            <div className="flex gap-2">
                                <EntryPicker
                                    value={home}
                                    placeholder="Choose an entry…"
                                    onChange={(picked) => { setHome(picked); form.setData('homepage_entry_id', picked[0]?.id ?? null); }}
                                />
                                {home.length > 0 && (
                                    <Button type="button" variant="ghost" size="icon" aria-label="Clear homepage"
                                        onClick={() => { setHome([]); form.setData('homepage_entry_id', null); }}>
                                        <X />
                                    </Button>
                                )}
                            </div>
                            <InputError message={errors.homepage_entry_id} />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Languages</CardTitle>
                    <CardDescription>The main language has unprefixed URLs; others are served under /&#123;code&#125;/….</CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-3">
                    {locales.available.map((code) => (
                        <div key={code} className="flex items-center gap-3">
                            <code className="w-16 text-sm">{code}</code>
                            <Input
                                className="max-w-60"
                                placeholder="Name, e.g. English"
                                value={locales.names[code] ?? ''}
                                onChange={(e) => setLocales({ names: { ...locales.names, [code]: e.target.value } })}
                            />
                            {code === locales.main ? (
                                <span className="text-xs font-medium text-muted-foreground">Main language</span>
                            ) : (
                                <>
                                    {!mainLocked && (
                                        <Button type="button" variant="ghost" size="sm" onClick={() => setLocales({ main: code })}>Make main</Button>
                                    )}
                                    <Button type="button" variant="ghost" size="icon" className="text-muted-foreground" aria-label={`Remove ${code}`} onClick={() => removeLocale(code)}>
                                        <X />
                                    </Button>
                                </>
                            )}
                        </div>
                    ))}
                    <div className="flex items-center gap-2">
                        <Input
                            className="w-32 font-mono"
                            placeholder="e.g. en"
                            value={newLocale}
                            onChange={(e) => setNewLocale(e.target.value)}
                            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); addLocale(); } }}
                        />
                        <Button type="button" variant="outline" size="sm" onClick={addLocale}><Plus /> Add language</Button>
                    </div>
                    {mainLocked && <p className="text-xs text-muted-foreground">The main language is fixed once content exists.</p>}
                    <InputError message={errors['locales.main'] ?? errors['locales.available'] ?? Object.entries(errors).find(([k]) => k.startsWith('locales.'))?.[1]} />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Search engines & sharing</CardTitle>
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                    <label className="flex items-start gap-3">
                        <Switch checked={form.data.seo.noindex} onCheckedChange={(v) => form.setData('seo', { ...form.data.seo, noindex: v })} />
                        <span className="grid gap-0.5 text-sm">
                            <span className="font-medium">Hide the whole site from search engines</span>
                            <span className="text-xs text-muted-foreground">Adds noindex to every page and empties the sitemap. Useful on staging.</span>
                        </span>
                    </label>
                    <div className="grid gap-2 sm:max-w-xs">
                        <Label htmlFor="twitter-site">X/Twitter handle</Label>
                        <Input id="twitter-site" placeholder="@acme" value={form.data.seo.twitter_site} onChange={(e) => form.setData('seo', { ...form.data.seo, twitter_site: e.target.value })} />
                        <InputError message={errors['seo.twitter_site']} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Default share image</Label>
                        <div className="flex items-center gap-3">
                            {image ? (
                                <div className="flex items-center gap-2 rounded-md border p-2 text-sm">
                                    <img src={image.url} alt="" className="size-10 rounded object-cover" />
                                    <span className="max-w-48 truncate">{image.filename}</span>
                                    <button type="button" className="text-muted-foreground hover:text-foreground" aria-label="Remove image"
                                        onClick={() => { setImage(null); form.setData('seo', { ...form.data.seo, image: null }); }}>
                                        <X className="size-4" />
                                    </button>
                                </div>
                            ) : (
                                <span className="flex items-center gap-2 text-sm text-muted-foreground"><ImageIcon className="size-4" /> None</span>
                            )}
                            <AssetPicker
                                imageOnly
                                trigger={<Button type="button" variant="outline" size="sm">Choose image</Button>}
                                onSelect={(assets: PickedAsset[]) => {
                                    const a = assets[0];
                                    if (!a) return;
                                    setImage({ id: a.id, url: a.url, filename: a.filename });
                                    form.setData('seo', { ...form.data.seo, image: a.id });
                                }}
                            />
                        </div>
                        <p className="text-xs text-muted-foreground">Shown when a page is shared and has no image of its own.</p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Code snippets</CardTitle>
                    <CardDescription>
                        Analytics, tag managers, verification tags… Added to every page through{' '}
                        <code>&lt;x-sunrice::code position="…" /&gt;</code> in your layout. Printed as-is.
                    </CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                    {([
                        ['head', 'In <head>', 'End of <head>: analytics, verification meta tags.'],
                        ['body_start', 'Start of <body>', 'Right after <body>: e.g. Google Tag Manager <noscript>.'],
                        ['body_end', 'End of <body>', 'Before </body>: chat widgets, deferred scripts.'],
                    ] as const).map(([key, label, help]) => (
                        <div key={key} className="grid gap-2">
                            <Label htmlFor={`code-${key}`}>{label}</Label>
                            <Textarea
                                id={`code-${key}`}
                                rows={4}
                                className="font-mono text-xs"
                                spellCheck={false}
                                value={form.data.code[key]}
                                onChange={(e) => form.setData('code', { ...form.data.code, [key]: e.target.value })}
                            />
                            <p className="text-xs text-muted-foreground">{help}</p>
                            <InputError message={errors[`code.${key}`]} />
                        </div>
                    ))}
                </CardContent>
            </Card>

            <div>
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <LoaderCircle className="animate-spin" />} Save settings
                </Button>
            </div>
        </form>
    );
}
