import * as React from 'react';
import { ChevronDown } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';

interface Props {
    title: React.ReactNode;
    description?: React.ReactNode;
    /** Remembers open/closed per browser under this key. */
    storageKey?: string;
    defaultOpen?: boolean;
    titleClassName?: string;
    contentClassName?: string;
    children: React.ReactNode;
}

const read = (key?: string): boolean | null => {
    if (!key) return null;
    try {
        const value = window.localStorage.getItem(`sunrice:card:${key}`);
        return value === null ? null : value === '1';
    } catch {
        return null;
    }
};

/** A card whose header folds its content away; the choice is remembered. */
export function CollapsibleCard({ title, description, storageKey, defaultOpen = true, titleClassName, contentClassName, children }: Props) {
    const [open, setOpen] = React.useState(() => read(storageKey) ?? defaultOpen);

    const change = (next: boolean) => {
        setOpen(next);
        if (!storageKey) return;
        try {
            window.localStorage.setItem(`sunrice:card:${storageKey}`, next ? '1' : '0');
        } catch {
            // Storage blocked: the card still works, it just won't remember.
        }
    };

    return (
        <Collapsible open={open} onOpenChange={change} asChild>
            <Card className={cn(!open && 'gap-0')}>
                <CardHeader>
                    <CollapsibleTrigger className="flex w-full cursor-pointer items-start justify-between gap-2 text-left">
                        <div className="grid gap-1.5">
                            <CardTitle className={titleClassName}>{title}</CardTitle>
                            {description && open && <CardDescription>{description}</CardDescription>}
                        </div>
                        <ChevronDown className={cn('mt-0.5 size-4 shrink-0 text-muted-foreground transition-transform', !open && '-rotate-90')} />
                    </CollapsibleTrigger>
                </CardHeader>
                <CollapsibleContent>
                    <CardContent className={contentClassName}>{children}</CardContent>
                </CollapsibleContent>
            </Card>
        </Collapsible>
    );
}
