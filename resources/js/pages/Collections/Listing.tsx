import * as React from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { useUnsavedChanges } from '@/lib/use-unsaved-changes';
import { ArrowLeft, ExternalLink } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { CollapsibleCard } from '@/components/app/collapsible-card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { InputError } from '@/components/app/input-error';
import FieldRenderer from '@/fields/FieldRenderer';
import { TranslationModeProvider } from '@/fields/translation-mode';
import { adminUrl } from '@/lib/route';
import type { AdminField, Json, SharedProps } from '@/types';

interface LocaleValues { title: string; intro: string; data: Record<string, Json> }

interface Props {
    collection: { id: number; handle: string; title: string; has_archive: boolean; urls: Record<string, string> };
    fields: AdminField[];
    values: Record<string, LocaleValues>;
    mainLocale: string;
    can: { edit: boolean; translate: boolean };
}

/** Listing page content: heading, intro and the listing blueprint's fields, per language. */
export default function ListingEdit({ collection, fields, values, mainLocale, can }: Props) {
    const { adminPath, locales } = usePage<SharedProps>().props;
    const [locale, setLocale] = React.useState(mainLocale);
    const [form, setForm] = React.useState<LocaleValues>(values[mainLocale]);
    const [errors, setErrors] = React.useState<Record<string, string>>({});
    const [processing, setProcessing] = React.useState(false);
    const secondary = locale !== mainLocale;
    const editable = secondary ? can.edit || can.translate : can.edit;

    // Load the language's saved values when switching tabs or after saving.
    // Keyed on the saved content: a failed save (same values) keeps the edits.
    const saved = JSON.stringify(values[locale] ?? { title: '', intro: '', data: {} });
    React.useEffect(() => {
        setForm(JSON.parse(saved));
        setErrors({});
    }, [locale, saved]);

    const formRef = React.useRef<HTMLFormElement>(null);
    const dirty = editable && JSON.stringify(form) !== saved;
    useUnsavedChanges(dirty && !processing, () => formRef.current?.requestSubmit());

    // Each language is saved separately: don't drop this one's edits silently.
    const switchLocale = (lc: string) => {
        if (lc === locale) return;
        if (dirty && !window.confirm(`You have unsaved changes in ${locale.toUpperCase()}. Switch language and discard them?`)) return;
        setLocale(lc);
    };

    const save = (e: React.FormEvent) => {
        e.preventDefault();
        router.put(adminUrl(`collections/${collection.handle}/listing`, adminPath), { locale, ...form } as unknown as Record<string, Json>, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => setErrors({}),
            onError: (e) => setErrors(e),
        });
    };

    return (
        <form ref={formRef} onSubmit={save} className="flex max-w-3xl flex-col gap-6">
            <div className="flex items-start justify-between gap-4">
                <div className="flex items-start gap-3">
                    <Button variant="outline" size="icon" className="size-8 shrink-0" asChild>
                        <Link href={adminUrl(`collections/${collection.handle}/entries`, adminPath)} aria-label={`Back to ${collection.title}`}>
                            <ArrowLeft />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="sunrice-page-title">{collection.title}: listing page</h1>
                        <p className="text-sm text-muted-foreground">The page that lists this collection's entries.</p>
                    </div>
                </div>
                {collection.has_archive && collection.urls[locale] && (
                    <Button variant="outline" size="sm" asChild>
                        <a href={collection.urls[locale]} target="_blank" rel="noopener">View <ExternalLink className="ml-1 size-3" /></a>
                    </Button>
                )}
            </div>

            {!collection.has_archive && (
                <p className="rounded-md border border-amber-500/50 p-3 text-sm">
                    This collection has no listing page yet. Turn on “Has a listing page” in its settings to publish this content.
                </p>
            )}

            {locales.available.length > 1 && (
                <Tabs value={locale} onValueChange={switchLocale} activationMode="manual">
                    <TabsList>
                        {locales.available.map((lc) => (
                            <TabsTrigger key={lc} value={lc}>{locales.names[lc] ?? lc.toUpperCase()}</TabsTrigger>
                        ))}
                    </TabsList>
                </Tabs>
            )}

            <div className="flex flex-col gap-6">
                <CollapsibleCard title="Heading &amp; intro" description={secondary && <>Empty ones use the {mainLocale.toUpperCase()} text.</>} storageKey="listing:heading-amp-intro" hasErrors={Object.keys(errors).length > 0} contentClassName="flex flex-col gap-4">
                    <fieldset disabled={!editable} className="flex min-w-0 flex-col gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="listing-title">Heading</Label>
                            <Input id="listing-title" value={form.title} placeholder={secondary ? values[mainLocale]?.title || collection.title : collection.title}
                                onChange={(e) => setForm({ ...form, title: e.target.value })} />
                            <InputError message={errors.title} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="listing-intro">Intro</Label>
                            <textarea id="listing-intro" rows={3} className="w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                                value={form.intro} placeholder={secondary ? values[mainLocale]?.intro : ''}
                                onChange={(e) => setForm({ ...form, intro: e.target.value })} />
                            <InputError message={errors.intro} />
                        </div>
                    </fieldset>
                </CollapsibleCard>

                {fields.length > 0 ? (
                    <CollapsibleCard title="Content" storageKey="listing:content" hasErrors={Object.keys(errors).length > 0}>
                        <fieldset disabled={!editable} className="min-w-0">
                            <TranslationModeProvider secondary={secondary} mainLocale={mainLocale}>
                                <FieldRenderer fields={fields} values={form.data} errors={errors} onChange={(data) => setForm({ ...form, data })} />
                            </TranslationModeProvider>
                        </fieldset>
                    </CollapsibleCard>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        Want more on this page, such as a hero image? Choose a listing blueprint in the collection's settings.
                    </p>
                )}
            </div>

            {editable && (
                <div><Button type="submit" disabled={processing}>{processing ? 'Saving…' : 'Save listing page'}</Button></div>
            )}
        </form>
    );
}
