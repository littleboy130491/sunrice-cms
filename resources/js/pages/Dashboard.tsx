import { Link, usePage } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { adminUrl } from '@/lib/route';
import type { SharedProps } from '@/types';

interface Props {
    collections: { handle: string; title: string; entries: number }[];
    recentEdits: { id: number; title: string; collection: string | null; collection_handle: string | null; status: string; updated_at: string | null }[];
    recentSubmissions: { id: number; form: string | null; created_at: string | null }[];
}

export default function Dashboard({ collections, recentEdits, recentSubmissions }: Props) {
    const { adminPath } = usePage<SharedProps>().props;

    return (
        <div className="flex flex-col gap-6">
            <h1 className="text-2xl font-semibold">Dashboard</h1>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {collections.map((c) => (
                    <Link key={c.handle} href={adminUrl(`collections/${c.handle}/entries`, adminPath)}>
                        <Card className="transition-colors hover:bg-accent">
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium text-muted-foreground">{c.title}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{c.entries}</div>
                            </CardContent>
                        </Card>
                    </Link>
                ))}
                {collections.length === 0 && (
                    <Card className="sm:col-span-2 lg:col-span-4">
                        <CardContent className="pt-6 text-sm text-muted-foreground">
                            No collections yet. Create one under Manage → Collections.
                        </CardContent>
                    </Card>
                )}
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Recent edits</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="flex flex-col gap-2">
                            {recentEdits.map((e) => (
                                <li key={e.id} className="flex items-center justify-between gap-2 text-sm">
                                    <Link href={adminUrl(`entries/${e.id}`, adminPath)} className="truncate hover:underline">
                                        {e.title}
                                    </Link>
                                    <span className="flex items-center gap-2 text-muted-foreground">
                                        <Badge variant={e.status === 'published' ? 'success' : 'secondary'}>{e.status}</Badge>
                                        {e.updated_at}
                                    </span>
                                </li>
                            ))}
                            {recentEdits.length === 0 && <li className="text-sm text-muted-foreground">No entries yet.</li>}
                        </ul>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Recent form submissions</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="flex flex-col gap-2">
                            {recentSubmissions.map((s) => (
                                <li key={s.id} className="flex items-center justify-between gap-2 text-sm">
                                    <span>{s.form ?? 'Form'}</span>
                                    <span className="text-muted-foreground">{s.created_at}</span>
                                </li>
                            ))}
                            {recentSubmissions.length === 0 && <li className="text-sm text-muted-foreground">No submissions yet.</li>}
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
