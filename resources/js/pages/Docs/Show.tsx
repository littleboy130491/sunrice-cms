import * as React from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import { adminUrl } from '@/lib/route';
import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types';

interface Props {
    page: string;
    title: string;
    html: string;
    headings: { id: string; text: string; level: number }[];
    sections: { label: string; pages: { slug: string; title: string }[] }[];
}

/** Developer docs: the package's docs/*.md, with a guide list and "On this page". */
export default function DocsShow({ page, title, html, headings, sections }: Props) {
    const { adminPath } = usePage<SharedProps>().props;
    useBreadcrumbs([{ label: 'Manage' }, { label: 'Docs', href: adminUrl('docs', adminPath) }, { label: title }]);

    // Links to other guides are plain <a> tags in the rendered HTML: visit
    // them through Inertia so the admin doesn't reload.
    const onClick = (e: React.MouseEvent) => {
        const a = (e.target as HTMLElement).closest('a');
        if (!a || e.metaKey || e.ctrlKey || e.shiftKey || a.target === '_blank') return;
        const url = new URL(a.href, window.location.href);
        if (url.origin !== window.location.origin || !url.pathname.startsWith(adminUrl('docs', adminPath))) return;
        if (url.pathname === window.location.pathname) return; // same-page anchor
        e.preventDefault();
        router.visit(url.pathname + url.hash);
    };

    // Land on the anchor after visiting "guide#section".
    React.useEffect(() => {
        const id = decodeURIComponent(window.location.hash.slice(1));
        if (id) document.getElementById(id)?.scrollIntoView();
        else window.scrollTo(0, 0);
    }, [page]);

    return (
        <div className="flex flex-col gap-6">
            <div className="space-y-1">
                <h1 className="sunrice-page-title">Docs</h1>
                <p className="text-sm text-muted-foreground">How to build and run a site on Sunrice: templates, commands, languages, mail and more.</p>
            </div>

            <div className="grid items-start gap-8 lg:grid-cols-[200px_minmax(0,1fr)] xl:grid-cols-[200px_minmax(0,1fr)_200px]">
                {/* Phones: pick a guide from a dropdown instead of the long list. */}
                <Select value={page} onValueChange={(slug) => router.visit(adminUrl(`docs/${slug}`, adminPath))}>
                    <SelectTrigger className="w-full lg:hidden" aria-label="Guide"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        {sections.map((section) => (
                            <SelectGroup key={section.label}>
                                <SelectLabel>{section.label}</SelectLabel>
                                {section.pages.map((p) => <SelectItem key={p.slug} value={p.slug}>{p.title}</SelectItem>)}
                            </SelectGroup>
                        ))}
                    </SelectContent>
                </Select>

                <nav aria-label="Guides" className="hidden flex-col gap-5 lg:sticky lg:top-6 lg:flex">
                    {sections.map((section) => (
                        <div key={section.label} className="flex flex-col gap-1">
                            <p className="px-2 text-[10px] font-semibold tracking-[0.14em] text-muted-foreground uppercase">{section.label}</p>
                            {section.pages.map((p) => (
                                <Link
                                    key={p.slug}
                                    href={adminUrl(`docs/${p.slug}`, adminPath)}
                                    className={cn(
                                        'rounded-md px-2 py-1 text-sm transition-colors hover:bg-muted',
                                        p.slug === page ? 'bg-muted font-medium text-foreground' : 'text-muted-foreground',
                                    )}
                                    aria-current={p.slug === page ? 'page' : undefined}
                                >
                                    {p.title}
                                </Link>
                            ))}
                        </div>
                    ))}
                </nav>

                <article
                    onClick={onClick}
                    className="sunrice-docs min-w-0 rounded-xl border bg-card px-5 py-6 sm:px-8"
                    dangerouslySetInnerHTML={{ __html: html }}
                />

                {headings.length > 0 && (
                    <nav aria-label="On this page" className="hidden flex-col gap-1 xl:sticky xl:top-6 xl:flex">
                        <p className="px-2 text-[10px] font-semibold tracking-[0.14em] text-muted-foreground uppercase">On this page</p>
                        {headings.map((h) => (
                            <a
                                key={h.id}
                                href={`#${h.id}`}
                                className={cn('rounded-md px-2 py-1 text-sm text-muted-foreground hover:bg-muted hover:text-foreground', h.level === 3 && 'pl-5 text-xs')}
                            >
                                {h.text}
                            </a>
                        ))}
                    </nav>
                )}
            </div>
        </div>
    );
}
