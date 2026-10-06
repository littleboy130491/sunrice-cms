import { Link, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { adminUrl } from '@/lib/route';
import { useCan } from '@/lib/can';
import type { SharedProps } from '@/types';

interface Props {
    forms: {
        id: number; handle: string; title: string; submissions_count: number;
        can: { edit: boolean; submissions: boolean; delete: boolean };
    }[];
}

export default function FormsIndex({ forms }: Props) {
    const { adminPath } = usePage<SharedProps>().props;
    const can = useCan();

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h1 className="text-xl font-semibold tracking-tight">Forms</h1>
                {can('sunrice.forms.create') && (
                    <Button asChild>
                        <Link href={adminUrl('forms/create', adminPath)}>
                            <Plus className="mr-1 h-4 w-4" /> New form
                        </Link>
                    </Button>
                )}
            </div>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Title</TableHead>
                        <TableHead className="max-md:hidden">Handle</TableHead>
                        <TableHead>Submissions</TableHead>
                        <TableHead className="w-48" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {forms.map((form) => (
                        <TableRow key={form.id}>
                            <TableCell className="font-medium">
                                {form.can.submissions || form.can.edit ? (
                                    <Link className="hover:underline" href={adminUrl(form.can.submissions ? `forms/${form.id}/submissions` : `forms/${form.handle}`, adminPath)}>
                                        {form.title}
                                    </Link>
                                ) : form.title}
                            </TableCell>
                            <TableCell className="text-muted-foreground max-md:hidden">{form.handle}</TableCell>
                            <TableCell>{form.submissions_count}</TableCell>
                            <TableCell className="flex justify-end gap-1">
                                {form.can.submissions && (
                                    <Button variant="ghost" size="sm" asChild>
                                        <Link href={adminUrl(`forms/${form.id}/submissions`, adminPath)}>Submissions</Link>
                                    </Button>
                                )}
                                {form.can.edit && (
                                    <Button variant="ghost" size="sm" asChild>
                                        <Link href={adminUrl(`forms/${form.handle}`, adminPath)}>Edit</Link>
                                    </Button>
                                )}
                                {form.can.delete && (
                                    <Button
                                        variant="ghost" size="sm" className="text-destructive"
                                        onClick={() => window.confirm(`Delete "${form.title}" and its submissions?`) && router.delete(adminUrl(`forms/${form.id}`, adminPath))}
                                    >Delete</Button>
                                )}
                            </TableCell>
                        </TableRow>
                    ))}
                    {forms.length === 0 && (
                        <TableRow>
                            <TableCell colSpan={4} className="text-center text-muted-foreground">
                                No forms yet.
                            </TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </Table>
        </div>
    );
}
