import { Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, FileText, Inbox, PenLine } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Heading } from '@/components/app/heading';
import { adminUrl } from '@/lib/route';
import type { SharedProps } from '@/types';

interface Props {
    collections: { handle: string; title: string; entries: number }[];
    recentEdits: { id: number; title: string; collection: string | null; collection_handle: string | null; status: string; updated_at: string | null }[];
    recentSubmissions: { id: number; form: string | null; created_at: string | null }[];
}

function EmptyState({ icon: Icon, children }: { icon: typeof FileText; children: React.ReactNode }) {
    return (
        <div className="flex flex-col items-center justify-center gap-2 py-8 text-center text-sm text-muted-foreground">
            <div className="flex size-10 items-center justify-center rounded-full bg-muted">
                <Icon className="size-4" />
            </div>
            {children}
        </div>
    );
}

export default function Dashboard({ collections, recentEdits, recentSubmissions }: Props) {
    const { adminPath, auth } = usePage<SharedProps>().props;
    const firstName = auth.user?.name?.split(' ')[0];

    return (
        <div className="flex flex-col gap-6">
            <Heading
                title={firstName ? `Welcome back, ${firstName}` : 'Dashboard'}
                description="An overview of your content and recent activity."
            />

            <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                {collections.map((c) => (
                    <Link key={c.handle} href={adminUrl(`collections/${c.handle}/entries`, adminPath)} className="group">
                        <Card className="gap-2 py-5 transition-colors group-hover:border-foreground/20 group-hover:bg-accent/40">
                            <CardHeader className="px-5">
                                <CardDescription className="flex items-center justify-between">
                                    {c.title}
                                    <ArrowUpRight className="size-4 opacity-0 transition-opacity group-hover:opacity-100" />
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="px-5">
                                <div className="text-3xl font-semibold tracking-tight tabular-nums">{c.entries}</div>
                                <p className="text-xs text-muted-foreground">{c.entries === 1 ? 'entry' : 'entries'}</p>
                            </CardContent>
                        </Card>
                    </Link>
                ))}
                {collections.length === 0 && (
                    <Card className="col-span-2 lg:col-span-4">
                        <CardContent>
                            <EmptyState icon={FileText}>No collections yet. Create one under Manage → Collections.</EmptyState>
                        </CardContent>
                    </Card>
                )}
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Recent edits</CardTitle>
                        <CardDescription>The latest entries changed across all collections.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {recentEdits.length === 0 ? (
                            <EmptyState icon={PenLine}>No entries yet.</EmptyState>
                        ) : (
                            <ul className="-mx-2 flex flex-col">
                                {recentEdits.map((e) => (
                                    <li key={e.id}>
                                        <Link
                                            href={adminUrl(`entries/${e.id}`, adminPath)}
                                            className="flex items-center justify-between gap-3 rounded-md px-2 py-2 text-sm transition-colors hover:bg-accent"
                                        >
                                            <div className="min-w-0">
                                                <div className="truncate font-medium">{e.title}</div>
                                                {e.collection && <div className="truncate text-xs text-muted-foreground">{e.collection}</div>}
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
                <Card>
                    <CardHeader>
                        <CardTitle>Recent form submissions</CardTitle>
                        <CardDescription>New messages from your public forms.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {recentSubmissions.length === 0 ? (
                            <EmptyState icon={Inbox}>No submissions yet.</EmptyState>
                        ) : (
                            <ul className="divide-y">
                                {recentSubmissions.map((s) => (
                                    <li key={s.id} className="flex items-center justify-between gap-2 py-2.5 text-sm">
                                        <span className="font-medium">{s.form ?? 'Form'}</span>
                                        <span className="text-xs text-muted-foreground">{s.created_at}</span>
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
