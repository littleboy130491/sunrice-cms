import { Link, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { adminUrl } from '@/lib/route';
import { useCan } from '@/lib/can';
import type { SharedProps } from '@/types';

interface Props {
    forms: { id: number; handle: string; title: string; submissions_count: number }[];
}

export default function FormsIndex({ forms }: Props) {
    const { adminPath } = usePage<SharedProps>().props;
    const can = useCan();

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h1 className="text-xl font-semibold">Forms</h1>
                {can('sunrice.manage-structure') && (
                    <Button asChild>
                        <Link href={adminUrl(adminPath, 'forms/create')}>
                            <Plus className="mr-1 h-4 w-4" /> New form
                        </Link>
                    </Button>
                )}
            </div>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Title</TableHead>
                        <TableHead>Handle</TableHead>
                        <TableHead>Submissions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {forms.map((form) => (
                        <TableRow
                            key={form.id}
                            className="cursor-pointer"
                            onClick={() => router.get(adminUrl(adminPath, `forms/${form.handle}`))}
                        >
                            <TableCell className="font-medium">{form.title}</TableCell>
                            <TableCell className="text-muted-foreground">{form.handle}</TableCell>
                            <TableCell>{form.submissions_count}</TableCell>
                        </TableRow>
                    ))}
                    {forms.length === 0 && (
                        <TableRow>
                            <TableCell colSpan={3} className="text-center text-muted-foreground">
                                No forms yet.
                            </TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </Table>
        </div>
    );
}
