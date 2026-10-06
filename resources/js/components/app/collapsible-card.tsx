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
            <Card className="gap-0 overflow-hidden py-0">
                <CardHeader className="px-0">
                    <CollapsibleTrigger
                        className={cn(
                            'group/trigger flex w-full cursor-pointer items-start justify-between gap-3 px-6 py-4 text-left transition-colors hover:bg-muted/40',
                            open && 'border-b border-border/70',
                        )}
                    >
                        <div className="grid gap-1">
                            <CardTitle className={titleClassName}>{title}</CardTitle>
                            {description && open && <CardDescription>{description}</CardDescription>}
                        </div>
                        <span className="mt-[-2px] grid size-6 shrink-0 place-items-center rounded-md text-muted-foreground transition-colors group-hover/trigger:bg-muted group-hover/trigger:text-foreground">
                            <ChevronDown className={cn('size-4 transition-transform duration-200', !open && '-rotate-90')} />
                        </span>
                    </CollapsibleTrigger>
                </CardHeader>
                <CollapsibleContent>
                    <CardContent className={cn('py-5', contentClassName)}>{children}</CardContent>
                </CollapsibleContent>
            </Card>
        </Collapsible>
    );
}
