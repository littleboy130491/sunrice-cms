import { Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { adminUrl } from '@/lib/route';
import type { SharedProps } from '@/types';

const MESSAGES: Record<number, { title: string; body: string }> = {
    403: { title: 'Forbidden', body: 'You do not have permission to view this page.' },
    404: { title: 'Not found', body: 'The page you are looking for does not exist.' },
    500: { title: 'Something went wrong', body: 'An unexpected error occurred.' },
};

export default function Error({ status }: { status: number }) {
    const { adminPath } = usePage<SharedProps>().props;
    const message = MESSAGES[status] ?? MESSAGES[500];

    return (
        <div className="flex min-h-[50vh] flex-col items-center justify-center gap-4 text-center">
            <div className="text-6xl font-bold text-muted-foreground">{status}</div>
            <h1 className="text-xl font-semibold">{message.title}</h1>
            <p className="text-sm text-muted-foreground">{message.body}</p>
            <Button asChild variant="outline">
                <Link href={adminUrl('', adminPath)}>Back to dashboard</Link>
            </Button>
        </div>
    );
}
