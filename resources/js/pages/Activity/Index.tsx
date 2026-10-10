import * as React from 'react';
import { router, usePage } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DataTable, type FilterDef } from '@/components/data-table/DataTable';
import { adminUrl } from '@/lib/route';
import type { ColumnDef, Paginated, SharedProps } from '@/types';

interface Row {
    id: number;
    created_at: string | null;
    user: string;
    source: string;
    action: string;
    activity: string;
    details: string;
}

interface Props {
    columns: ColumnDef[];
    rows: Paginated<Row>;
    meta: { search: string | null; filters: Record<string, string>; sort: string | null };
    filters: FilterDef[];
    can: { prune: boolean };
    pruneDays: number;
}

export default function ActivityIndex({ columns, rows, meta, filters, can, pruneDays }: Props) {
    const { adminPath } = usePage<SharedProps>().props;
    const [pruning, setPruning] = React.useState(false);
    const [days, setDays] = React.useState(String(pruneDays));
    const [error, setError] = React.useState<string | undefined>();
    const [processing, setProcessing] = React.useState(false);

    function prune(e: React.FormEvent) {
        e.preventDefault();
        router.post(adminUrl('activity/prune', adminPath), { days }, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => { setPruning(false); setError(undefined); },
            onError: (errors) => setError(errors.days),
        });
    }

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between gap-3">
                <h1 className="sunrice-page-title">Activity log</h1>
                {can.prune && (
                    <Button variant="outline" onClick={() => setPruning(true)}>
                        <Trash2 className="mr-1 h-4 w-4" /> Prune
                    </Button>
                )}
            </div>
            <DataTable
                columns={columns}
                rows={rows}
                meta={meta}
                tableKey="activity"
                filters={filters}
                searchPlaceholder="Search by name, user or token…"
            />

            <Dialog open={pruning} onOpenChange={setPruning}>
                <DialogContent>
                    <form onSubmit={prune} className="grid gap-4">
                        <DialogHeader>
                            <DialogTitle>Prune the activity log</DialogTitle>
                            <DialogDescription>Delete entries older than a number of days. This can't be undone.</DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-2">
                            <Label htmlFor="prune-days">Older than (days)</Label>
                            <Input id="prune-days" type="number" min={0} value={days} onChange={(e) => setDays(e.target.value)} required />
                            {error && <p className="text-sm text-destructive">{error}</p>}
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setPruning(false)}>Cancel</Button>
                            <Button type="submit" variant="destructive" disabled={processing}>{processing ? 'Deleting…' : 'Delete old entries'}</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
