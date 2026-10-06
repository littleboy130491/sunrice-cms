import { Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, FileText, Inbox, PenLine, Layers } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { AtmosphereArt } from '@/components/app/atmosphere-art';
import { adminUrl } from '@/lib/route';
import type { SharedProps } from '@/types';

interface Props {
    collections: { handle: string; title: string; entries: number }[];
    recentEdits: { id: number; title: string; collection: string | null; collection_handle: string | null; status: string; updated_at: string | null }[];
    recentSubmissions: { id: number; form_id: number; form: string | null; summary: string | null; created_at: string | null }[];
}

function EmptyState({ icon: Icon, children }: { icon: typeof FileText; children: React.ReactNode }) {
    return (
        <div className="flex flex-col items-center justify-center gap-3 py-12 text-center text-sm text-muted-foreground">
            <div className="flex size-12 items-center justify-center rounded-2xl bg-muted">
                <Icon className="size-5" />
            </div>
            {children}
        </div>
    );
}

export default function Dashboard({ collections, recentEdits, recentSubmissions }: Props) {
    const { adminPath, auth } = usePage<SharedProps>().props;
    const firstName = auth.user?.name?.split(' ')[0];
    const entryCount = collections.reduce((total, collection) => total + collection.entries, 0);

    return (
        <div className="flex flex-col gap-8">
            <section className="sunrice-atmosphere relative isolate overflow-hidden rounded-3xl border border-border/40 px-6 py-8 md:px-10 md:py-10">
                <div className="relative z-10 max-w-xl md:max-w-[65%]">
                    <p className="mb-5 flex items-center gap-2 text-[10px] font-semibold tracking-[0.16em] uppercase"><span className="size-1.5 rounded-full bg-current" /> Your workspace, at a glance</p>
                    <h1 className="text-3xl leading-tight font-medium tracking-[-0.045em] md:text-4xl">{firstName ? `Welcome back, ${firstName}.` : 'Your content starts here.'}</h1>
                    <p className="mt-3 max-w-sm text-sm leading-relaxed opacity-75">A little clarity for your next idea. Here’s what’s happening with your content.</p>
                    <div className="mt-8 flex flex-wrap gap-x-8 gap-y-4 border-t border-current/10 pt-5">
                        <div><span className="text-2xl font-medium tracking-tight tabular-nums">{entryCount}</span><span className="ml-2 text-xs opacity-70">{entryCount === 1 ? 'entry' : 'entries'}</span></div>
                        <div><span className="text-2xl font-medium tracking-tight tabular-nums">{collections.length}</span><span className="ml-2 text-xs opacity-70">{collections.length === 1 ? 'collection' : 'collections'}</span></div>
                    </div>
                </div>
                <AtmosphereArt className="absolute top-0 right-[-3rem] hidden h-full w-[40%] md:block" />
            </section>

            <section className="space-y-4" aria-labelledby="collections-heading">
                <div className="flex items-center justify-between gap-3">
                    <h2 id="collections-heading" className="text-lg font-medium tracking-tight">Your collections</h2>
                    <span className="text-xs text-muted-foreground">A home for every kind of content</span>
                </div>
                <div className="grid grid-cols-[repeat(auto-fit,minmax(min(100%,14rem),1fr))] gap-4">
                    {collections.map((c) => (
                        <Link key={c.handle} href={adminUrl(`collections/${c.handle}/entries`, adminPath)} className="group rounded-2xl outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background">
                            <Card className="gap-5 py-5 transition-all duration-200 group-hover:-translate-y-0.5 group-hover:border-ring/30 group-hover:shadow-md">
                                <CardHeader className="px-5">
                                    <CardDescription className="flex items-center justify-between">
                                        <span className="flex min-w-0 items-center gap-2.5"><span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-secondary text-secondary-foreground"><Layers className="size-4" /></span><span className="truncate font-medium text-foreground">{c.title}</span></span>
                                        <ArrowUpRight className="size-4 shrink-0 text-muted-foreground transition-colors group-hover:text-foreground" />
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="px-5">
                                    <div className="flex items-baseline gap-2"><span className="text-3xl font-medium tracking-tight tabular-nums">{c.entries}</span><span className="text-xs text-muted-foreground">{c.entries === 1 ? 'entry' : 'entries'}</span></div>
                                </CardContent>
                            </Card>
                        </Link>
                    ))}
                    {collections.length === 0 && (
                        <Card className="col-span-full">
                            <CardContent>
                                <EmptyState icon={FileText}>No collections yet. Create one under Manage → Collections.</EmptyState>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </section>

            <div className="grid gap-5 lg:grid-cols-[1.2fr_1fr]">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2"><PenLine className="size-4 text-muted-foreground" /> Recent edits</CardTitle>
                        <CardDescription>The latest entries changed across all collections.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {recentEdits.length === 0 ? (
                            <EmptyState icon={PenLine}>No entries yet.</EmptyState>
                        ) : (
                            <ul className="-mx-2 flex flex-col divide-y divide-border/60">
                                {recentEdits.map((e) => (
                                    <li key={e.id}>
                                        <Link
                                            href={adminUrl(`entries/${e.id}`, adminPath)}
                                            className="flex items-center justify-between gap-3 rounded-lg px-2 py-3.5 text-sm transition-colors hover:bg-accent"
                                        >
                                            <div className="min-w-0">
                                                <div className="truncate font-medium">{e.title}</div>
                                                {e.collection && <div className="mt-1 truncate text-xs text-muted-foreground">{e.collection}</div>}
                                            </div>
                                            <div className="flex shrink-0 items-center gap-3 text-xs text-muted-foreground">
                                                <Badge variant={e.status === 'published' ? 'success' : 'secondary'} className="capitalize">
                                                    {e.status}
                                                </Badge>
                                                <span className="hidden sm:inline">{e.updated_at}</span>
                                            </div>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
                <Card className="bg-card/70">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2"><Inbox className="size-4 text-muted-foreground" /> Recent form submissions</CardTitle>
                        <CardDescription>New messages from your public forms.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {recentSubmissions.length === 0 ? (
                            <EmptyState icon={Inbox}>No submissions yet.</EmptyState>
                        ) : (
                            <ul className="flex flex-col divide-y divide-border/60">
                                {recentSubmissions.map((s) => (
                                    <li key={s.id}>
                                        <Link
                                            href={adminUrl(`forms/${s.form_id}/submissions`, adminPath)}
                                            className="-mx-2 flex items-center justify-between gap-3 rounded-lg px-2 py-3.5 text-sm transition-colors hover:bg-accent"
                                        >
                                            <span className="min-w-0">
                                                <span className="block truncate font-medium">{s.summary ?? 'New submission'}</span>
                                                <span className="mt-1 block text-xs text-muted-foreground">{s.form ?? 'Form'}</span>
                                            </span>
                                            <span className="shrink-0 text-xs text-muted-foreground">{s.created_at}</span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
