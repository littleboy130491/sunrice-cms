import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { adminUrl } from '@/lib/route';
import { useCan } from '@/lib/can';
import type { SharedProps } from '@/types';
import * as React from 'react';

interface Props {
    form: { id: number; handle: string; title: string; fields: Record<string, unknown>[] };
    submissions: {
        data: { id: number; data: Record<string, unknown>; created_at: string }[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { search?: string; from?: string; to?: string };
}

export default function Submissions({ form, submissions, filters }: Props) {
    const { adminPath } = usePage<SharedProps>().props;
    const can = useCan();
    const [search, setSearch] = React.useState(filters.search ?? '');
    const handles = form.fields.map((f) => f.handle as string);

    function apply() {
        router.get(adminUrl(adminPath, `forms/${form.id}/submissions`), { search }, { preserveState: true });
    }

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h1 className="text-xl font-semibold">Submissions: {form.title}</h1>
                {can(`sunrice.forms.${form.id}.export-submissions`) && (
                    <Button variant="outline" asChild>
                        <a href={adminUrl(adminPath, `forms/${form.id}/submissions/export`)}>Export CSV</a>
                    </Button>
                )}
            </div>
            <div className="flex gap-2">
                <Input
                    placeholder="Search…"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    onKeyDown={(e) => e.key === 'Enter' && apply()}
                    className="max-w-xs"
                />
                <Button variant="secondary" onClick={apply}>Filter</Button>
            </div>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>#</TableHead>
                        <TableHead>Submitted</TableHead>
                        {handles.map((h) => <TableHead key={h}>{h}</TableHead>)}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {submissions.data.map((s) => (
                        <TableRow key={s.id}>
                            <TableCell>{s.id}</TableCell>
                            <TableCell>{s.created_at}</TableCell>
                            {handles.map((h) => (
                                <TableCell key={h}>{String(s.data[h] ?? '')}</TableCell>
                            ))}
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
