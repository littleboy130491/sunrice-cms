import { Link, router, usePage } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { adminUrl } from '@/lib/route';
import { useCan } from '@/lib/can';
import type { SharedProps } from '@/types';
import * as React from 'react';

interface Submission {
    id: number;
    data: Record<string, unknown>;
    created_at: string;
    ip_address?: string | null;
    user_agent?: string | null;
    locale?: string | null;
}

interface Props {
    form: { id: number; handle: string; title: string; fields: Record<string, unknown>[] };
    submissions: {
        data: Submission[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { search?: string; from?: string; to?: string };
}

/** Table cells are cut to this many characters; the popup shows everything. */
const CELL_LIMIT = 60;

/** A submitted value as text (checkbox groups and other lists joined). */
function asText(value: unknown): string {
    if (value === null || value === undefined) return '';
    if (Array.isArray(value)) return value.map(asText).filter(Boolean).join(', ');
    if (typeof value === 'boolean') return value ? 'Yes' : 'No';
    if (typeof value === 'object') return JSON.stringify(value);
    return String(value);
}

function shorten(text: string, limit = CELL_LIMIT): string {
    const flat = text.replace(/\s+/g, ' ').trim();
    return flat.length > limit ? `${flat.slice(0, limit).trimEnd()}…` : flat;
}

const formatDate = (iso: string) => new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });

export default function Submissions({ form, submissions, filters }: Props) {
    const { adminPath } = usePage<SharedProps>().props;
    const can = useCan();
    const [search, setSearch] = React.useState(filters.search ?? '');
    const [open, setOpen] = React.useState<Submission | null>(null);
    const handles = form.fields.map((f) => f.handle as string);
    const fileFields = new Set(form.fields.filter((f) => f.type === 'file').map((f) => f.handle as string));
    const labelOf = (h: string) => (form.fields.find((f) => f.handle === h)?.label as string) || h;
    const canDelete = can(`sunrice.forms.${form.id}.delete-submissions`);
    const remove = (id: number) =>
        window.confirm(`Delete submission #${id}? Its uploaded files are deleted too.`)
        && router.delete(adminUrl(`submissions/${id}`, adminPath), { preserveScroll: true, onSuccess: () => setOpen(null) });
    const download = (s: Submission, h: string) => (
        // Uploaded files are private: download through the admin.
        <a className="underline" href={adminUrl(`submissions/${s.id}/download/${h}`, adminPath)} onClick={(e) => e.stopPropagation()}>Download</a>
    );

    function apply() {
        router.get(adminUrl(`forms/${form.id}/submissions`, adminPath), { search }, { preserveState: true });
    }

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h1 className="sunrice-page-title">Submissions: {form.title}</h1>
                <div className="flex gap-2">
                    {can(`sunrice.forms.${form.id}.edit`) && (
                        <Button variant="outline" asChild>
                            <Link href={adminUrl(`forms/${form.handle}`, adminPath)}>Edit form</Link>
                        </Button>
                    )}
                    {can(`sunrice.forms.${form.id}.export-submissions`) && (
                        <Button variant="outline" asChild>
                            <a href={adminUrl(`forms/${form.id}/submissions/export`, adminPath)}>Export CSV</a>
                        </Button>
                    )}
                </div>
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
                        {handles.map((h) => <TableHead key={h}>{labelOf(h)}</TableHead>)}
                        {canDelete && <TableHead className="w-12" />}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {submissions.data.map((s) => (
                        <TableRow
                            key={s.id}
                            className="cursor-pointer"
                            tabIndex={0}
                            aria-label={`View submission #${s.id}`}
                            onClick={() => setOpen(s)}
                            onKeyDown={(e) => e.key === 'Enter' && e.target === e.currentTarget && setOpen(s)}
                        >
                            <TableCell>{s.id}</TableCell>
                            <TableCell className="whitespace-nowrap" title={s.created_at}>{formatDate(s.created_at)}</TableCell>
                            {handles.map((h) => (
                                <TableCell key={h} className="max-w-xs">
                                    {fileFields.has(h) && s.data[h] ? download(s, h) : <span className="block truncate">{shorten(asText(s.data[h]))}</span>}
                                </TableCell>
                            ))}
                            {canDelete && (
                                <TableCell onClick={(e) => e.stopPropagation()}>
                                    <Button variant="ghost" size="icon" className="text-destructive" aria-label={`Delete submission #${s.id}`} onClick={() => remove(s.id)}>
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </TableCell>
                            )}
                        </TableRow>
                    ))}
                    {submissions.data.length === 0 && (
                        <TableRow>
                            <TableCell colSpan={handles.length + 2} className="h-24 text-center text-muted-foreground">No submissions{filters.search ? ' match your search' : ' yet'}.</TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </Table>
            {submissions.data.length > 0 && <p className="text-sm text-muted-foreground">Click a submission to see everything that was sent.</p>}
            <Dialog open={open !== null} onOpenChange={(o) => !o && setOpen(null)}>
                <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                    {open && (
                        <>
                            <DialogHeader>
                                <DialogTitle>Submission #{open.id}</DialogTitle>
                                <DialogDescription>{form.title} · {formatDate(open.created_at)}</DialogDescription>
                            </DialogHeader>
                            <dl className="divide-y">
                                {handles.map((h) => (
                                    <div key={h} className="grid gap-1 py-3 sm:grid-cols-[10rem_1fr] sm:gap-4">
                                        <dt className="text-sm font-medium text-muted-foreground">{labelOf(h)}</dt>
                                        <dd className="text-sm break-words whitespace-pre-wrap">
                                            {fileFields.has(h) && open.data[h] ? download(open, h) : asText(open.data[h]) || <span className="text-muted-foreground">—</span>}
                                        </dd>
                                    </div>
                                ))}
                                {/* Values kept from fields the form no longer has. */}
                                {Object.keys(open.data).filter((k) => !handles.includes(k)).map((k) => (
                                    <div key={k} className="grid gap-1 py-3 sm:grid-cols-[10rem_1fr] sm:gap-4">
                                        <dt className="text-sm font-medium text-muted-foreground">{k} <span className="font-normal">(removed field)</span></dt>
                                        <dd className="text-sm break-words whitespace-pre-wrap">{asText(open.data[k]) || '—'}</dd>
                                    </div>
                                ))}
                            </dl>
                            {(open.ip_address || open.locale || open.user_agent) && (
                                <div className="space-y-1 rounded-md bg-muted/50 p-3 text-xs text-muted-foreground">
                                    {open.locale && <p>Language: {open.locale}</p>}
                                    {open.ip_address && <p>IP address: {open.ip_address}</p>}
                                    {open.user_agent && <p className="break-words">Browser: {open.user_agent}</p>}
                                </div>
                            )}
                            {canDelete && (
                                <div className="flex justify-end">
                                    <Button variant="outline" className="text-destructive" onClick={() => remove(open.id)}>
                                        <Trash2 className="h-4 w-4" /> Delete submission
                                    </Button>
                                </div>
                            )}
                        </>
                    )}
                </DialogContent>
            </Dialog>
            {submissions.last_page > 1 && (
                <div className="flex items-center justify-between text-sm text-muted-foreground">
                    <span>Page {submissions.current_page} of {submissions.last_page} · {submissions.total} submissions</span>
                    <div className="flex gap-2">
                        <Button variant="outline" size="sm" disabled={!submissions.prev_page_url} onClick={() => submissions.prev_page_url && router.get(submissions.prev_page_url, {}, { preserveScroll: true })}>Previous</Button>
                        <Button variant="outline" size="sm" disabled={!submissions.next_page_url} onClick={() => submissions.next_page_url && router.get(submissions.next_page_url, {}, { preserveScroll: true })}>Next</Button>
                    </div>
                </div>
            )}
        </div>
    );
}
